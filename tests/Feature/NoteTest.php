<?php

use Easyreply\Inbox\Models\Note;

it('adds an internal note to a conversation, separate from the customer thread', function () {
    [$team, $user] = createTeamWithAgent();
    $conversation = conversationForTeam($team);

    $response = $this->actingAs($user)->postJson("/shared-inbox/conversations/{$conversation->id}/notes", [
        'body' => 'Customer is a VIP, escalate if needed.',
    ]);

    $response->assertCreated();
    expect(Note::where('conversation_id', $conversation->id)->count())->toBe(1);

    // Notes never appear in the customer-facing message thread.
    expect($conversation->messages()->count())->toBe(0);
});

it('only records mentioned_user_ids for users who actually belong to the team', function () {
    [$team, $user] = createTeamWithAgent();
    [, $outsider] = createTeamWithAgent();
    $conversation = conversationForTeam($team);

    $this->actingAs($user)->postJson("/shared-inbox/conversations/{$conversation->id}/notes", [
        'body' => 'cc @teammate',
        'mentioned_user_ids' => [$user->id, $outsider->id],
    ])->assertCreated();

    $note = Note::where('conversation_id', $conversation->id)->first();
    expect($note->mentioned_user_ids)->toBe([$user->id]);
});

it('404s adding a note to another team\'s conversation', function () {
    [, $userA] = createTeamWithAgent();
    $teamB = \Easyreply\Inbox\Models\Team::factory()->create();
    $foreignConversation = conversationForTeam($teamB);

    $this->actingAs($userA)
        ->postJson("/shared-inbox/conversations/{$foreignConversation->id}/notes", ['body' => 'sneaky'])
        ->assertNotFound();
});
