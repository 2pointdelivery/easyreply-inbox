<?php

namespace Easyreply\Inbox\Channels\Data;

/**
 * Channel-agnostic shape a ChannelDriver normalizes a raw provider webhook
 * payload into, before it's turned into Contact/Conversation/Message rows.
 */
final class InboundMessageData
{
    public function __construct(
        public readonly string $channelType,
        public readonly string $fromExternalId,
        public readonly ?string $fromDisplayName,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly string $externalId,
        public readonly ?string $inReplyToExternalId,
        public readonly array $rawPayload,
    ) {}
}
