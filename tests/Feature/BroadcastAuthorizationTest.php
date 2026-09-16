<?php

use Easyreply\Inbox\Broadcasting\AuthorizeConversationChannel;
use Easyreply\Inbox\Broadcasting\AuthorizeTeamChannel;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;

it('authorizes a user who belongs to the team', function () {
    [$team, $user] = createTeamWithAgent();

    expect((new AuthorizeTeamChannel)($user, $team->id))->toBeTrue();
});

it('denies a user who does not belong to the team', function () {
    $team = Team::factory()->create();
    $user = createTestUser();

    expect((new AuthorizeTeamChannel)($user, $team->id))->toBeFalse();
});

it('authorizes a user who belongs to the conversation\'s team', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id]);

    expect((new AuthorizeConversationChannel)($user, $conversation->id))->toBeTrue();
});

it('denies a user who does not belong to the conversation\'s team', function () {
    $otherTeam = Team::factory()->create();
    $inbox = Inbox::factory()->for($otherTeam)->email()->create();
    $conversation = Conversation::factory()->for($otherTeam)->create(['inbox_id' => $inbox->id]);

    [$team, $user] = createTeamWithAgent();

    expect((new AuthorizeConversationChannel)($user, $conversation->id))->toBeFalse();
});

it('denies when the conversation does not exist', function () {
    [$team, $user] = createTeamWithAgent();

    expect((new AuthorizeConversationChannel)($user, 999999))->toBeFalse();
});
