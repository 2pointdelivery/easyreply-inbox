<?php

namespace Easyreply\Inbox\Ai;

use Easyreply\Inbox\Ai\Contracts\AiReplyDriver;
use Easyreply\Inbox\Ai\Data\DraftReplyData;
use Easyreply\Inbox\Models\Conversation;

/**
 * The safe default when no AI provider is configured
 * (config('shared-inbox.ai.driver') = 'null'). The package works fully
 * without AI drafting; this driver just means the "AI draft" action has
 * nothing to offer.
 */
class NullAiDriver implements AiReplyDriver
{
    public function draftReply(Conversation $conversation): ?DraftReplyData
    {
        return null;
    }
}
