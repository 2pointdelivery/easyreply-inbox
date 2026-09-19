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
 * ElevenLabs conversational-AI agent driver. Normalizes ElevenLabs
 * post-call webhooks (transcript + metadata) and dials via the
 * ElevenLabs outbound API. Never fetches or stores audio.
 */
class ElevenLabsVoiceDriver implements VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData
    {
        $data = $payload['data'] ?? $payload;

        return new InboundCallData(
            provider: 'elevenlabs',
            providerCallId: (string) ($data['conversation_id'] ?? $data['call_id'] ?? Str::uuid()),
            direction: ($data['direction'] ?? 'inbound') === 'outbound' ? CallLog::DIRECTION_OUTBOUND : CallLog::DIRECTION_INBOUND,
            fromE164: (string) ($data['caller_id'] ?? $data['from'] ?? ''),
            toE164: (string) ($data['called_number'] ?? $data['to'] ?? ''),
            status: $this->mapStatus($data['status'] ?? 'completed'),
            transcript: $data['transcript'] ?? null,
            summary: $data['summary'] ?? null,
            sentiment: $data['sentiment'] ?? null,
            durationSeconds: isset($data['duration_secs']) ? (int) $data['duration_secs'] : null,
            rawPayload: $payload,
        );
    }

    public function initiateOutbound(CallLog $call, OutboundCallData $data): string
    {
        $apiKey = config('shared-inbox.voice.elevenlabs.api_key');
        $agentId = config('shared-inbox.voice.elevenlabs.agent_id');

        $response = Http::withToken($apiKey)->asJson()->post('https://api.elevenlabs.io/v1/convai/batch-calls', [
            'agent_id' => $agentId,
            'recipients' => [['phone_number' => $data->toE164]],
            'prompt_context' => $data->context,
        ]);

        return (string) ($response->json('batch_id') ?? $response->json('call_id') ?? 'elevenlabs-'.Str::uuid());
    }

    public function transferCall(string $providerCallId, string $targetE164): bool
    {
        $apiKey = config('shared-inbox.voice.elevenlabs.api_key');

        if (! $apiKey) {
            return false;
        }

        $response = Http::withToken($apiKey)->asJson()->post(
            "https://api.elevenlabs.io/v1/convai/conversations/{$providerCallId}/transfer",
            ['phone_number' => $targetE164]
        );

        return $response->successful();
    }

    public function endCall(string $providerCallId): void
    {
        $apiKey = config('shared-inbox.voice.elevenlabs.api_key');

        if ($apiKey) {
            Http::withToken($apiKey)->delete("https://api.elevenlabs.io/v1/convai/conversations/{$providerCallId}");
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.voice.elevenlabs.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signature = $request->header('ElevenLabs-Signature') ?? $request->header('X-ElevenLabs-Signature');

        if (! $signature) {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    public function worthProcessing(array $payload): bool
    {
        $data = $payload['data'] ?? $payload;

        // ElevenLabs also delivers test pings, status heartbeats, and
        // agent-preview events on the same webhook — only real call
        // completions / transcripts reach the database.
        if (($payload['type'] ?? null) === 'ping') {
            return false;
        }

        return ! empty($data['conversation_id']) || ! empty($data['call_id']) || ! empty($data['transcript']);
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'done', 'ended' => CallLog::STATUS_COMPLETED,
            'ringing' => CallLog::STATUS_RINGING,
            'in-progress', 'ongoing' => CallLog::STATUS_IN_PROGRESS,
            'busy' => CallLog::STATUS_BUSY,
            'no-answer', 'no_answer' => CallLog::STATUS_NO_ANSWER,
            'failed', 'error' => CallLog::STATUS_FAILED,
            default => CallLog::STATUS_COMPLETED,
        };
    }
}
