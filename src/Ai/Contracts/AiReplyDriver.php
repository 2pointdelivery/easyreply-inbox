<?php

namespace Easyreply\Inbox\Ai\Contracts;

use Easyreply\Inbox\Ai\Data\DraftReplyData;
use Easyreply\Inbox\Models\Conversation;

interface AiReplyDriver
{
    /**
     * Draft a suggested reply for the conversation's latest message, using
     * its history for context. Returns null when a draft isn't available
     * (no provider configured, or the provider call failed) — callers must
     * treat null as "nothing to show", never fabricate a draft on failure.
     */
    public function draftReply(Conversation $conversation): ?DraftReplyData;
}
