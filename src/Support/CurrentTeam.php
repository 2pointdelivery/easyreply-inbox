<?php

namespace Easyreply\Inbox\Support;

use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Support\Contracts\CurrentTeam as CurrentTeamContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Default CurrentTeam resolver: the team stashed in session for this user, if
 * they still belong to it, otherwise the first team the authenticated user
 * belongs to. Returns null for guests or users with no team.
 */
class CurrentTeam implements CurrentTeamContract
{
    protected const SESSION_KEY = 'shared_inbox_current_team_id';

    public function resolve(): ?Team
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $teamId = Session::get(self::SESSION_KEY);

        if ($teamId && $team = $user->teams()->find($teamId)) {
            return $team;
        }

        return $user->teams()->first();
    }

    public function set(Team $team): void
    {
        Session::put(self::SESSION_KEY, $team->id);
    }
}
