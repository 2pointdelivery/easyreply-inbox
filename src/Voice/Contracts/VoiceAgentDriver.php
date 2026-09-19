<?php

namespace Easyreply\Inbox\Voice\Contracts;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Voice\Data\InboundCallData;
use Easyreply\Inbox\Voice\Data\OutboundCallData;
use Illuminate\Http\Request;

/**
 * Provider-agnostic voice agent driver (ElevenLabs, OpenAI Realtime,
 * Aircall, Vapi/Retell/Bland via the generic-SIP driver).
 *
 * Strict policy: drivers must NEVER fetch, persist, or return raw audio
 * bytes or recording URLs. Transcripts + summaries + metadata only.
 */
interface VoiceAgentDriver
{
    public function normalizeInbound(array $payload): InboundCallData;

    public function initiateOutbound(CallLog $call, OutboundCallData $data): string;

    public function transferCall(string $providerCallId, string $targetE164): bool;

    public function endCall(string $providerCallId): void;

    public function verifyWebhookSignature(Request $request): bool;

    public function worthProcessing(array $payload): bool;
}
