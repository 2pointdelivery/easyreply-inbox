<?php

namespace Easyreply\Inbox\Http\Middleware;

use Closure;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every shared-inbox web route: without this, a user who belongs to
 * no team would hit TeamScope's "nothing to constrain by, so don't filter"
 * behavior (see Models/Scopes/TeamScope.php) and see every team's data
 * unscoped. TeamScope alone is meant for contexts with no authenticated
 * user at all (queued jobs, webhooks) — for actual UI requests, the current
 * team must resolve to something, or the request is refused outright.
 */
class EnsureTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(CurrentTeam::class)->resolve()) {
            abort(403, 'You do not belong to a team.');
        }

        return $next($request);
    }
}
