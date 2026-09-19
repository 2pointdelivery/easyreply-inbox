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
 * Aircall driver: Aircall owns the cloud phone number + transfer fabric,
 * while the conversational brain stays with ElevenLabs/OpenAI. This driver
 * normalizes Aircall call webhooks and performs live SIP/PSTN transfers.
 */
class AircallVoiceDriver implements VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData
    {
        $data = $payload['data'] ?? $payload;

        return new InboundCallData(
            provider: 'aircall',
            providerCallId: (string) ($data['id'] ?? $data['call_id'] ?? Str::uuid()),
            direction: ($data['direction'] ?? 'inbound') === 'outbound' ? CallLog::DIRECTION_OUTBOUND : CallLog::DIRECTION_INBOUND,
            fromE164: (string) ($data['raw_digits'] ?? $data['from'] ?? ''),
            toE164: (string) ($data['number']['digits'] ?? $data['to'] ?? ''),
            status: $this->mapStatus($data['status'] ?? 'done'),
            transcript: $data['transcript'] ?? null,
            summary: $data['summary'] ?? $data['comments'] ?? null,
            sentiment: $data['sentiment'] ?? null,
            durationSeconds: isset($data['duration']) ? (int) $data['duration'] : null,
            rawPayload: $payload,
        );
    }

    public function initiateOutbound(CallLog $call, OutboundCallData $data): string
    {
        $apiKey = config('shared-inbox.voice.aircall.api_key');

        $response = Http::withToken($apiKey)->asJson()->post('https://api.aircall.io/v1/calls', [
            'to' => $data->toE164,
            'from' => config('shared-inbox.voice.shared_number_e164'),
        ]);

        return (string) ($response->json('data.id') ?? $response->json('id') ?? 'aircall-'.Str::uuid());
    }

    public function transferCall(string $providerCallId, string $targetE164): bool
    {
        $apiKey = config('shared-inbox.voice.aircall.api_key');

        if (! $apiKey) {
            return false;
        }

        $response = Http::withToken($apiKey)->asJson()->post(
            "https://api.aircall.io/v1/calls/{$providerCallId}/transfers",
            ['to' => $targetE164]
        );

        return $response->successful();
    }

    public function endCall(string $providerCallId): void
    {
        $apiKey = config('shared-inbox.voice.aircall.api_key');

        if ($apiKey) {
            Http::withToken($apiKey)->delete("https://api.aircall.io/v1/calls/{$providerCallId}");
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.voice.aircall.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signature = $request->header('X-Aircall-Signature');

        if (! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function worthProcessing(array $payload): bool
    {
        $event = strtolower((string) ($payload['event'] ?? ''));

        if (in_array($event, ['ping', 'test', 'heartbeat'], true)) {
            return false;
        }

        $data = $payload['data'] ?? $payload;

        // Aircall emits comment/note/tag events on the same webhook — only
        // real call events create logs.
        if ($event !== '' && ! str_contains($event, 'call')) {
            return false;
        }

        return ! empty($data['id']) || ! empty($data['call_id']) || ! empty($data['transcript']);
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'done', 'completed', 'ended' => CallLog::STATUS_COMPLETED,
            'ringing', 'initial' => CallLog::STATUS_RINGING,
            'ongoing', 'in-progress', 'answered' => CallLog::STATUS_IN_PROGRESS,
            'busy' => CallLog::STATUS_BUSY,
            'no-answer', 'no_answer', 'missed' => CallLog::STATUS_NO_ANSWER,
            'failed', 'error', 'voicemail' => CallLog::STATUS_FAILED,
            default => CallLog::STATUS_COMPLETED,
        };
    }
}
