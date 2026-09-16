<?php

namespace Easyreply\Inbox\Models\Scopes;

use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentTeam = app(CurrentTeam::class)->resolve();

        if ($currentTeam) {
            $builder->where($model->qualifyColumn('team_id'), $currentTeam->id);
        }
    }
}
