<?php

namespace Easyreply\Inbox\Voice;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Voice\Contracts\VoiceAgentDriver;
use Easyreply\Inbox\Voice\Data\InboundCallData;
use Easyreply\Inbox\Voice\Data\OutboundCallData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Generic SIP/webhook driver covering Vapi, Retell, and Bland with one
 * shared shape plus per-vendor payload normalizers. Keeps v1 to a single
 * driver instead of three near-duplicates; split only if vendor flows
 * diverge beyond payload shape.
 */
class GenericSipVoiceDriver implements VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData
    {
        $vendor = $this->detectVendor($payload);
        $data = $this->extractData($payload, $vendor);

        return new InboundCallData(
            provider: $vendor,
            providerCallId: (string) ($data['call_id'] ?? Str::uuid()),
            direction: ($data['direction'] ?? 'inbound') === 'outbound' ? CallLog::DIRECTION_OUTBOUND : CallLog::DIRECTION_INBOUND,
            fromE164: (string) ($data['from'] ?? ''),
            toE164: (string) ($data['to'] ?? ''),
            status: $this->mapStatus($data['status'] ?? 'completed'),
            transcript: $data['transcript'] ?? null,
            summary: $data['summary'] ?? null,
            sentiment: $data['sentiment'] ?? null,
            durationSeconds: isset($data['duration']) ? (int) $data['duration'] : null,
            rawPayload: $payload,
        );
    }

    public function initiateOutbound(CallLog $call, OutboundCallData $data): string
    {
        // Generic driver has no single dial endpoint — the team's provider
        // webhook URL handles the dial. Record a local reference instead.
        return 'sip-'.Str::uuid();
    }

    public function transferCall(string $providerCallId, string $targetE164): bool
    {
        $transferUrl = config('shared-inbox.voice.transfer.webhook_url');

        if (! $transferUrl) {
            return false;
        }

        $response = Http::asJson()->post($transferUrl, [
            'call_id' => $providerCallId,
            'transfer_to' => $targetE164,
        ]);

        return $response->successful();
    }

    public function endCall(string $providerCallId): void {}

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.voice.generic_sip.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signature = $request->header('X-Voice-Signature') ?? $request->header('X-Webhook-Secret');

        if (! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function worthProcessing(array $payload): bool
    {
        $type = strtolower((string) ($payload['type'] ?? $payload['event'] ?? ''));

        // Vendors emit status heartbeats, test hooks, and recording-only
        // events on the same webhook — none of those create call logs.
        if (in_array($type, ['ping', 'test', 'heartbeat', 'recording.available'], true)) {
            return false;
        }

        $data = $this->extractData($payload, $this->detectVendor($payload));

        return ! empty($data['call_id']) || ! empty($data['transcript']);
    }

    protected function detectVendor(array $payload): string
    {
        if (isset($payload['call']['call_id']) || isset($payload['vapi_call_id'])) {
            return 'vapi';
        }

        if (isset($payload['retell_call_id']) || isset($payload['call_id']) && isset($payload['retell_llm_id'])) {
            return 'retell';
        }

        if (isset($payload['bland_call_id']) || isset($payload['c_id'])) {
            return 'bland';
        }

        return 'generic-sip';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractData(array $payload, string $vendor): array
    {
        return match ($vendor) {
            'vapi' => [
                'call_id' => $payload['call']['call_id'] ?? $payload['vapi_call_id'] ?? null,
                'from' => $payload['call']['customer']['number'] ?? $payload['from'] ?? null,
                'to' => $payload['call']['phoneNumber']['number'] ?? $payload['to'] ?? null,
                'status' => $payload['call']['status'] ?? $payload['status'] ?? null,
                'direction' => $payload['call']['direction'] ?? $payload['direction'] ?? null,
                'transcript' => $payload['call']['transcript'] ?? $payload['transcript'] ?? null,
                'summary' => $payload['call']['summary'] ?? $payload['summary'] ?? null,
                'sentiment' => $payload['call']['sentiment'] ?? null,
                'duration' => $payload['call']['duration'] ?? null,
            ],
            'retell' => [
                'call_id' => $payload['retell_call_id'] ?? $payload['call_id'] ?? null,
                'from' => $payload['from_number'] ?? $payload['from'] ?? null,
                'to' => $payload['to_number'] ?? $payload['to'] ?? null,
                'status' => $payload['call_status'] ?? $payload['status'] ?? null,
                'direction' => $payload['direction'] ?? null,
                'transcript' => $payload['transcript'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'sentiment' => $payload['sentiment'] ?? null,
                'duration' => $payload['duration_ms'] ?? null,
            ],
            'bland' => [
                'call_id' => $payload['bland_call_id'] ?? $payload['c_id'] ?? null,
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
                'status' => $payload['status'] ?? null,
                'direction' => $payload['direction'] ?? null,
                'transcript' => $payload['concatenated_transcript'] ?? $payload['transcript'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'sentiment' => $payload['sentiment'] ?? null,
                'duration' => $payload['call_length'] ?? null,
            ],
            default => [
                'call_id' => $payload['call_id'] ?? null,
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
                'status' => $payload['status'] ?? null,
                'direction' => $payload['direction'] ?? null,
                'transcript' => $payload['transcript'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'sentiment' => $payload['sentiment'] ?? null,
                'duration' => $payload['duration_seconds'] ?? null,
            ],
        };
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower((string) $status)) {
            'completed', 'done', 'ended' => CallLog::STATUS_COMPLETED,
            'ringing' => CallLog::STATUS_RINGING,
            'in-progress', 'ongoing', 'in_progress' => CallLog::STATUS_IN_PROGRESS,
            'busy' => CallLog::STATUS_BUSY,
            'no-answer', 'no_answer' => CallLog::STATUS_NO_ANSWER,
            'failed', 'error' => CallLog::STATUS_FAILED,
            default => CallLog::STATUS_COMPLETED,
        };
    }
}
