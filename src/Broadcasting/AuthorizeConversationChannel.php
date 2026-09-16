<?php

namespace Easyreply\Inbox\Broadcasting;

use Easyreply\Inbox\Models\Conversation;

/**
 * Authorizes a user for the shared-inbox.conversation.{conversationId}
 * private channel — only members of the conversation's team may subscribe.
 * Looks up the conversation without the team global scope since the
 * subscribing user's "current team" isn't necessarily the conversation's
 * team yet at this point — that's exactly what's being checked.
 */
class AuthorizeConversationChannel
{
    public function __invoke($user, int|string $conversationId): bool
    {
        $conversation = Conversation::withoutGlobalScopes()->find($conversationId);

        return $conversation
            && method_exists($user, 'belongsToTeam')
            && $user->belongsToTeam($conversation->team);
    }
}
