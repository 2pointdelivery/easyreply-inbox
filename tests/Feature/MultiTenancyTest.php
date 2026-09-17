<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;

it('never lists another team\'s conversations in the inbox index', function () {
    [$teamA, $userA] = createTeamWithAgent();
    $teamB = Team::factory()->create();

    conversationForTeam($teamA);
    conversationForTeam($teamB);
    conversationForTeam($teamB);

    $response = $this->actingAs($userA)->get('/shared-inbox');

    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Index')
        ->has('conversations.data', 1)
    );
});

it('404s viewing another team\'s conversation directly by id', function () {
    [$teamA, $userA] = createTeamWithAgent();
    $teamB = Team::factory()->create();

    $foreignConversation = conversationForTeam($teamB);

    $this->actingAs($userA)
        ->get("/shared-inbox/conversations/{$foreignConversation->id}")
        ->assertNotFound();
});

it('404s replying to another team\'s conversation', function () {
    [$teamA, $userA] = createTeamWithAgent();
    $teamB = Team::factory()->create();

    $foreignConversation = conversationForTeam($teamB);

    $this->actingAs($userA)
        ->postJson("/shared-inbox/conversations/{$foreignConversation->id}/messages", ['body' => 'sneaky'])
        ->assertNotFound();
});

it('scopes Eloquent queries for Inbox, Contact, and Conversation to the current team', function () {
    [$teamA, $userA] = createTeamWithAgent();
    $teamB = Team::factory()->create();

    Inbox::factory()->for($teamA)->email()->create();
    Inbox::factory()->for($teamB)->email()->create();
    Contact::factory()->for($teamA)->create();
    Contact::factory()->for($teamB)->create();
    conversationForTeam($teamA);
    conversationForTeam($teamB);

    $this->actingAs($userA);

    // Each side also gets its own extra Inbox/Contact from conversationForTeam().
    expect(Inbox::count())->toBe(2)
        ->and(Contact::count())->toBe(2)
        ->and(Conversation::count())->toBe(1);
});

it('refuses a user with no team, rather than falling through to an unscoped view', function () {
    // TeamScope only constrains queries when a current team resolves (see
    // Models/Scopes/TeamScope.php) — deliberately, so queued jobs and
    // webhooks with no authenticated user still work. For an actual UI
    // request that means a teamless user must be refused explicitly
    // (EnsureTeamContext middleware), or they'd see every team's data
    // completely unscoped. This is the regression test for that.
    $user = createTestUser();
    $team = Team::factory()->create();
    conversationForTeam($team);

    $this->actingAs($user)->get('/shared-inbox')->assertForbidden();

    expect(Conversation::count())->toBe(1);
});
