<?php

use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Easyreply\Inbox\Tests\Fixtures\User;

function makeUser(): User
{
    return User::create([
        'name' => 'Jordan Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ]);
}

it('creates a team via its factory', function () {
    $team = Team::factory()->create(['name' => 'Acme Support']);

    expect($team->exists)->toBeTrue()
        ->and($team->name)->toBe('Acme Support')
        ->and($team->slug)->not->toBeEmpty();
});

it('attaches a user to a team with a role via team_user', function () {
    $team = Team::factory()->create();
    $user = makeUser();

    $team->users()->attach($user->id, ['role' => 'owner']);

    expect($user->teams()->count())->toBe(1)
        ->and($user->belongsToTeam($team))->toBeTrue()
        ->and($user->roleOnTeam($team))->toBe('owner');
});

it('resolves the current team for the authenticated user', function () {
    $team = Team::factory()->create();
    $user = makeUser();
    $team->users()->attach($user->id, ['role' => 'agent']);

    $this->actingAs($user);

    $resolved = app(CurrentTeam::class)->resolve();

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($team->id);
});

it('returns null for a guest with no authenticated user', function () {
    expect(app(CurrentTeam::class)->resolve())->toBeNull();
});

it('remembers the team set via CurrentTeam::set across resolves', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $user = makeUser();
    $team = $teamA->users()->attach($user->id, ['role' => 'agent']);
    $teamB->users()->attach($user->id, ['role' => 'agent']);

    $this->actingAs($user);

    app(CurrentTeam::class)->set($teamB);

    expect(app(CurrentTeam::class)->resolve()->id)->toBe($teamB->id);
});
