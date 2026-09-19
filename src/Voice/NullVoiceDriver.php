<?php

namespace Easyreply\Inbox\Voice;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Voice\Contracts\VoiceAgentDriver;
use Easyreply\Inbox\Voice\Data\InboundCallData;
use Easyreply\Inbox\Voice\Data\OutboundCallData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Safe default: accepts normalized payloads for logging but never dials,
 * never transfers, never calls a provider. Lets the call-log UI and
 * post-call actions work with zero voice config.
 */
class NullVoiceDriver implements VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData
    {
        return new InboundCallData(
            provider: 'null',
            providerCallId: (string) ($payload['call_id'] ?? Str::uuid()),
            direction: $payload['direction'] ?? CallLog::DIRECTION_INBOUND,
            fromE164: (string) ($payload['from'] ?? ''),
            toE164: (string) ($payload['to'] ?? ''),
            status: (string) ($payload['status'] ?? CallLog::STATUS_COMPLETED),
            transcript: $payload['transcript'] ?? null,
            summary: $payload['summary'] ?? null,
            sentiment: $payload['sentiment'] ?? null,
            durationSeconds: isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null,
            rawPayload: $payload,
        );
    }

    public function initiateOutbound(CallLog $call, OutboundCallData $data): string
    {
        return 'null-'.Str::uuid();
    }

    public function transferCall(string $providerCallId, string $targetE164): bool
    {
        return false;
    }

    public function endCall(string $providerCallId): void {}

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function worthProcessing(array $payload): bool
    {
        return ! empty($payload['call_id']) || ! empty($payload['from']);
    }
}
