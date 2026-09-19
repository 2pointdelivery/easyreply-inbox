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
 * OpenAI Realtime voice-agent driver (speech-to-speech over SIP/PSTN).
 * Normalizes OpenAI call webhooks and dials via the Realtime calls API.
 * Transcript text only — never audio.
 */
class OpenAiRealtimeVoiceDriver implements VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData
    {
        $data = $payload['data'] ?? $payload;

        return new InboundCallData(
            provider: 'openai-realtime',
            providerCallId: (string) ($data['call_id'] ?? $data['id'] ?? Str::uuid()),
            direction: ($data['direction'] ?? 'inbound') === 'outbound' ? CallLog::DIRECTION_OUTBOUND : CallLog::DIRECTION_INBOUND,
            fromE164: (string) ($data['from'] ?? $data['caller'] ?? ''),
            toE164: (string) ($data['to'] ?? $data['callee'] ?? ''),
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
        $apiKey = config('shared-inbox.voice.openai.realtime_api_key');

        $response = Http::withToken($apiKey)->asJson()->post('https://api.openai.com/v1/realtime/calls', [
            'to' => $data->toE164,
            'from' => config('shared-inbox.voice.shared_number_e164'),
            'instructions' => $data->context,
        ]);

        return (string) ($response->json('id') ?? $response->json('call_id') ?? 'openai-'.Str::uuid());
    }

    public function transferCall(string $providerCallId, string $targetE164): bool
    {
        $apiKey = config('shared-inbox.voice.openai.realtime_api_key');

        if (! $apiKey) {
            return false;
        }

        $response = Http::withToken($apiKey)->asJson()->post(
            "https://api.openai.com/v1/realtime/calls/{$providerCallId}/transfer",
            ['to' => $targetE164]
        );

        return $response->successful();
    }

    public function endCall(string $providerCallId): void
    {
        $apiKey = config('shared-inbox.voice.openai.realtime_api_key');

        if ($apiKey) {
            Http::withToken($apiKey)->delete("https://api.openai.com/v1/realtime/calls/{$providerCallId}");
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.voice.openai.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signature = $request->header('OpenAI-Signature') ?? $request->header('X-OpenAI-Signature');

        if (! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function worthProcessing(array $payload): bool
    {
        if (($payload['type'] ?? null) === 'ping') {
            return false;
        }

        $data = $payload['data'] ?? $payload;

        return ! empty($data['call_id']) || ! empty($data['id']) || ! empty($data['transcript']);
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'done', 'ended' => CallLog::STATUS_COMPLETED,
            'ringing' => CallLog::STATUS_RINGING,
            'in-progress', 'ongoing', 'active' => CallLog::STATUS_IN_PROGRESS,
            'busy' => CallLog::STATUS_BUSY,
            'no-answer', 'no_answer' => CallLog::STATUS_NO_ANSWER,
            'failed', 'error' => CallLog::STATUS_FAILED,
            default => CallLog::STATUS_COMPLETED,
        };
    }
}
