<?php

use Easyreply\Inbox\Models\SlaPolicy;

it('lets a team admin create an sla policy and applies it to new conversations', function () {
    [$team, $user] = createTeamWithAgent();
    $team->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $this->actingAs($user)->post('/shared-inbox/settings/sla-policies', [
        'priority' => 'normal',
        'first_response_minutes' => 30,
        'resolution_minutes' => 120,
    ])->assertRedirect();

    expect(SlaPolicy::where('team_id', $team->id)->where('priority', 'normal')->exists())->toBeTrue();

    $conversation = conversationForTeam($team);
    expect($conversation->sla_due_at)->not->toBeNull();
});

it('refuses a non-admin creating an sla policy', function () {
    [, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->post('/shared-inbox/settings/sla-policies', [
            'priority' => 'high',
            'first_response_minutes' => 15,
            'resolution_minutes' => 60,
        ])
        ->assertForbidden();
});

it('recomputes sla_due_at when a conversation\'s priority changes', function () {
    [$team, $user] = createTeamWithAgent();
    $team->users()->updateExistingPivot($user->id, ['role' => 'owner']);

    SlaPolicy::factory()->for($team)->create([
        'priority' => 'urgent',
        'first_response_minutes' => 10,
        'resolution_minutes' => 60,
    ]);

    $conversation = conversationForTeam($team);
    expect($conversation->sla_due_at)->toBeNull();

    $this->actingAs($user)
        ->patchJson("/shared-inbox/conversations/{$conversation->id}", ['priority' => 'urgent'])
        ->assertOk();

    expect($conversation->fresh()->sla_due_at)->not->toBeNull();
});

it('sets resolved_at when a conversation is closed', function () {
    [$team, $user] = createTeamWithAgent();
    $conversation = conversationForTeam($team);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/conversations/{$conversation->id}", ['status' => 'closed'])
        ->assertOk();

    $fresh = $conversation->fresh();
    expect($fresh->status)->toBe('closed')
        ->and($fresh->resolved_at)->not->toBeNull();
});
