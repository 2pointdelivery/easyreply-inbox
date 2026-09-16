<?php

namespace Easyreply\Inbox\Tests\Fixtures;

use Easyreply\Inbox\Concerns\BelongsToTeams;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Stand-in for the host app's own User model, used only in package tests.
 * Uses Laravel's default `users` table (via loadLaravelMigrations() in
 * TestCase) plus the trait real host apps add to their own User model.
 */
class User extends Authenticatable
{
    use BelongsToTeams;

    protected $table = 'users';

    protected $guarded = [];
}
