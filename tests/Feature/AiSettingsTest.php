<?php

use Easyreply\Inbox\Ai\Contracts\AiReplyDriver;
use Easyreply\Inbox\Ai\Data\DraftReplyData;
use Easyreply\Inbox\Models\Conversation;

class FakeOverrideAiDriver implements AiReplyDriver
{
    public function draftReply(Conversation $conversation): ?DraftReplyData
    {
        return new DraftReplyData(body: 'from the overridden driver');
    }
}

beforeEach(function () {
    config([
        'shared-inbox.ai.driver' => 'null',
        'shared-inbox.ai.drivers.fake_override' => FakeOverrideAiDriver::class,
    ]);
});

it('lets a team override its ai driver, used instead of the global default', function () {
    [$team, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->patch('/shared-inbox/settings/ai', ['ai_driver' => 'fake_override'])
        ->assertRedirect();

    expect($team->fresh()->ai_driver)->toBe('fake_override');

    $conversation = conversationForTeam($team);

    $response = $this->actingAs($user)->postJson("/shared-inbox/conversations/{$conversation->id}/ai-draft");

    $response->assertCreated();
    expect($response->json('draft.body'))->toBe('from the overridden driver');
});

it('clears the override back to the global default', function () {
    [$team, $user] = createTeamWithAgent();
    $team->update(['ai_driver' => 'fake_override']);

    $this->actingAs($user)
        ->patch('/shared-inbox/settings/ai', ['ai_driver' => ''])
        ->assertRedirect();

    expect($team->fresh()->ai_driver)->toBeNull();
});
