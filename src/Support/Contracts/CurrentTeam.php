<?php

namespace Easyreply\Inbox\Support\Contracts;

use Easyreply\Inbox\Models\Team;

/**
 * Resolves the "active" team for the current request. The package binds its
 * own default implementation (session + authenticated user's first team);
 * override `config('shared-inbox.current_team_resolver')` if the host app
 * already has a tenancy/session concept of "current team" to defer to.
 */
interface CurrentTeam
{
    public function resolve(): ?Team;

    public function set(Team $team): void;
}
