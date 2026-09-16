<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;

it('renders the inbox index page with a conversation summary', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create(['display_name' => 'Casey Customer']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
        'subject' => 'Help with my order',
    ]);
    $conversation->messages()->create([
        'direction' => Message::DIRECTION_INBOUND,
        'channel' => 'email',
        'sender_type' => Contact::class,
        'sender_id' => $contact->id,
        'body' => 'Where is my order?',
        'status' => Message::STATUS_SENT,
    ]);

    $response = $this->actingAs($user)->get('/shared-inbox');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Index')
        ->has('conversations.data', 1)
        ->where('conversations.data.0.subject', 'Help with my order')
        ->where('conversations.data.0.contact.display_name', 'Casey Customer')
        ->where('conversations.data.0.preview', 'Where is my order?')
    );
});

it('filters the inbox index page by status', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'status' => 'open']);
    Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'status' => 'closed']);

    $response = $this->actingAs($user)->get('/shared-inbox?status=closed');

    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Index')
        ->has('conversations.data', 1)
        ->where('conversations.data.0.status', 'closed')
        ->where('filters.status', 'closed')
    );
});

it('renders the conversation show page with its message thread', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create(['display_name' => 'Casey Customer']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
        'subject' => 'Help with my order',
    ]);
    $conversation->messages()->create([
        'direction' => Message::DIRECTION_INBOUND,
        'channel' => 'email',
        'sender_type' => Contact::class,
        'sender_id' => $contact->id,
        'body' => 'Where is my order?',
        'status' => Message::STATUS_SENT,
    ]);

    $response = $this->actingAs($user)->get("/shared-inbox/conversations/{$conversation->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Show')
        ->where('conversation.subject', 'Help with my order')
        ->where('conversation.contact.display_name', 'Casey Customer')
        ->has('conversation.messages', 1)
        ->where('conversation.messages.0.body', 'Where is my order?')
    );
});
