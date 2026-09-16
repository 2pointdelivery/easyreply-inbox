<?php

namespace Easyreply\Inbox\Broadcasting;

use Easyreply\Inbox\Models\Team;

/**
 * Authorizes a user for the shared-inbox.team.{teamId} private channel —
 * only members of that team may subscribe.
 */
class AuthorizeTeamChannel
{
    public function __invoke($user, int|string $teamId): bool
    {
        return method_exists($user, 'belongsToTeam')
            && $user->belongsToTeam(Team::findOrNew($teamId));
    }
}
