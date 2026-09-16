<?php

namespace Easyreply\Inbox\Concerns;

use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Applied to the host app's own User model (the package does not define or
 * migrate a `users` table — see BUILD_PROMPT.md §11). Gives the host app's
 * user access to the teams they belong to and their role on each.
 *
 *     use Easyreply\Inbox\Concerns\BelongsToTeams;
 *
 *     class User extends Authenticatable
 *     {
 *         use BelongsToTeams;
 *     }
 */
trait BelongsToTeams
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->whereKey($team->id)->exists();
    }

    public function roleOnTeam(Team $team): ?string
    {
        $pivot = $this->teams()->whereKey($team->id)->first()?->pivot;

        return $pivot?->role;
    }
}
