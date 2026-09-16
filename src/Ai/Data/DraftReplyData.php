<?php

namespace Easyreply\Inbox\Ai\Data;

final class DraftReplyData
{
    public function __construct(
        public readonly string $body,
    ) {}
}
