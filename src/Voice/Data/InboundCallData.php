<?php

namespace Easyreply\Inbox\Voice\Data;

/**
 * Channel-agnostic shape a VoiceAgentDriver normalizes a raw provider
 * webhook payload into. No audio bytes or recording URLs ever appear here —
 * transcript text only (strict no-audio policy).
 */
final class InboundCallData
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerCallId,
        public readonly string $direction,
        public readonly string $fromE164,
        public readonly string $toE164,
        public readonly string $status,
        public readonly ?string $transcript = null,
        public readonly ?string $summary = null,
        public readonly ?string $sentiment = null,
        public readonly ?int $durationSeconds = null,
        public readonly ?string $transferTarget = null,
        public readonly array $rawPayload = [],
    ) {}
}
