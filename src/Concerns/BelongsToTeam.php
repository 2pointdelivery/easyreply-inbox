<?php

namespace Easyreply\Inbox\Concerns;

use Easyreply\Inbox\Models\Scopes\TeamScope;
use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every team-scoped package model (Inbox, Conversation, Label,
 * etc. — added from build phase 3 onward). Auto-scopes queries to the
 * current team and stamps `team_id` on create when it isn't set explicitly.
 *
 * Requires a `team_id` column on the model's table.
 */
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope);

        static::creating(function (Model $model) {
            if (! $model->getAttribute('team_id')) {
                $currentTeam = app(CurrentTeam::class)->resolve();

                if ($currentTeam) {
                    $model->setAttribute('team_id', $currentTeam->id);
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
