<?php

use Easyreply\Inbox\Models\Label;

it('lets a team admin create a label', function () {
    [$team, $user] = createTeamWithAgent();
    $team->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $response = $this->actingAs($user)->post('/shared-inbox/settings/labels', [
        'name' => 'Billing',
        'color' => '#ff0000',
    ]);

    $response->assertRedirect();
    expect(Label::where('team_id', $team->id)->where('name', 'Billing')->exists())->toBeTrue();
});

it('refuses a non-admin agent creating a label', function () {
    [, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->post('/shared-inbox/settings/labels', ['name' => 'Billing'])
        ->assertForbidden();

    expect(Label::count())->toBe(0);
});

it('attaches and detaches a label on a conversation', function () {
    [$team, $user] = createTeamWithAgent();
    $team->users()->updateExistingPivot($user->id, ['role' => 'owner']);
    $label = Label::factory()->for($team)->create();
    $conversation = conversationForTeam($team);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/labels/{$label->id}")
        ->assertOk();

    expect($conversation->labels()->count())->toBe(1);

    $this->actingAs($user)
        ->deleteJson("/shared-inbox/conversations/{$conversation->id}/labels/{$label->id}")
        ->assertOk();

    expect($conversation->labels()->count())->toBe(0);
});
