<?php

namespace Easyreply\Inbox\Channels\Data;

final class OutboundMessageData
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $subject = null,
    ) {}
}
