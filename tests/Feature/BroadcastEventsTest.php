<?php

use Easyreply\Inbox\Events\ConversationAssigned;
use Easyreply\Inbox\Events\ConversationUpdated;
use Easyreply\Inbox\Events\MessageReceived;
use Easyreply\Inbox\Events\MessageSent;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Support\Facades\Event;

it('fires MessageReceived with correct channels when an inbound webhook creates a message', function () {
    config(['shared-inbox.channels.email.webhook_secret' => null]);
    Event::fake([MessageReceived::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", [
        'FromFull' => ['Email' => 'customer@example.com', 'Name' => 'Casey Customer'],
        'Subject' => 'Help with my order',
        'TextBody' => 'Where is my order?',
        'MessageID' => 'msg-1',
        'Headers' => [],
    ])->assertNoContent();

    Event::assertDispatched(MessageReceived::class, function (MessageReceived $event) {
        $channels = collect($event->broadcastOn())->map->name;

        return $event->message->direction === 'inbound'
            && $event->broadcastAs() === 'MessageReceived'
            && $channels->contains(fn ($name) => str_contains($name, 'shared-inbox.conversation.'))
            && $channels->contains(fn ($name) => str_contains($name, 'shared-inbox.team.'));
    });
});

it('fires MessageSent when an agent sends a reply', function () {
    Event::fake([MessageSent::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'customer@example.com']);
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id]);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", ['body' => 'On it!'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->direction === 'outbound'
        && $event->broadcastAs() === 'MessageSent');
});

it('fires MessageSent when a draft is finalized and sent', function () {
    Event::fake([MessageSent::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'customer@example.com']);
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id]);
    $draft = $conversation->messages()->create([
        'direction' => 'outbound',
        'channel' => 'email',
        'body' => 'Draft text',
        'ai_generated' => true,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/messages/{$draft->id}/send")
        ->assertOk();

    Event::assertDispatched(MessageSent::class);
});

it('fires both ConversationAssigned and ConversationUpdated when the assignee changes', function () {
    Event::fake([ConversationAssigned::class, ConversationUpdated::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id]);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/conversations/{$conversation->id}", ['assignee_id' => $user->id])
        ->assertOk();

    Event::assertDispatched(ConversationAssigned::class, fn (ConversationAssigned $event) => $event->conversation->id === $conversation->id);
    Event::assertDispatched(ConversationUpdated::class);
});

it('fires only ConversationUpdated when the status changes without an assignee change', function () {
    Event::fake([ConversationAssigned::class, ConversationUpdated::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'status' => 'open']);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/conversations/{$conversation->id}", ['status' => 'closed'])
        ->assertOk();

    Event::assertDispatched(ConversationUpdated::class);
    Event::assertNotDispatched(ConversationAssigned::class);
});

it('fires no events when the update is a no-op', function () {
    Event::fake([ConversationAssigned::class, ConversationUpdated::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'status' => 'open']);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/conversations/{$conversation->id}", ['status' => 'open'])
        ->assertOk();

    Event::assertNotDispatched(ConversationUpdated::class);
    Event::assertNotDispatched(ConversationAssigned::class);
});
