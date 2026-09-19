<?php

namespace Easyreply\Inbox\Voice\Data;

/**
 * Outbound dial request shape. The driver dials $toE164 from the team's
 * (or global) voice number and attaches $context for the agent prompt.
 */
final class OutboundCallData
{
    public function __construct(
        public readonly string $toE164,
        public readonly ?string $context = null,
        public readonly ?int $conversationId = null,
    ) {}
}
