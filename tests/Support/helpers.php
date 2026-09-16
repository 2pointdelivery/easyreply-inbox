<?php

use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Tests\Fixtures\User;

function createTestUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Jordan Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ], $overrides));
}

/**
 * @return array{0: Team, 1: User}
 */
function createTeamWithAgent(?Team $team = null): array
{
    $team ??= Team::factory()->create();
    $user = createTestUser();
    $team->users()->attach($user->id, ['role' => 'agent']);

    return [$team, $user];
}
