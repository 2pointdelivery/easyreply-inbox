<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'shared-inbox.channels.instagram.enabled' => true,
        'shared-inbox.channels.instagram.app_secret' => null,
    ]);
});

it('completes the meta webhook verification handshake', function () {
    config(['shared-inbox.channels.instagram.verify_token' => 'verify-me']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->instagram()->create();

    $response = $this->get("/shared-inbox/webhooks/instagram/{$inbox->id}?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=xyz");

    $response->assertOk();
    expect($response->getContent())->toBe('xyz');
});

it('creates a contact and conversation from an inbound instagram dm', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->instagram()->create();

    $payload = [
        'entry' => [[
            'messaging' => [[
                'sender' => ['id' => 'ig-user-1'],
                'message' => ['mid' => 'mid.ABC', 'text' => 'Do you ship internationally?'],
            ]],
        ]],
    ];

    $this->postJson("/shared-inbox/webhooks/instagram/{$inbox->id}", $payload)->assertNoContent();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::count())->toBe(1);

    expect(Conversation::first()->messages()->first()->body)->toBe('Do you ship internationally?');
});

it('ignores instagram callbacks with no message', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->instagram()->create();

    $payload = ['entry' => [['messaging' => [['sender' => ['id' => 'ig-user-1'], 'read' => ['mid' => 'mid.ABC']]]]]];

    $this->postJson("/shared-inbox/webhooks/instagram/{$inbox->id}", $payload)->assertNoContent();

    expect(Conversation::count())->toBe(0);
});

it('sends an outbound instagram reply', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['message_id' => 'mid.OUT'])]);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->instagram()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'instagram', 'external_id' => 'ig-user-1']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $user = createTestUser();
    $team->users()->attach($user->id, ['role' => 'agent']);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", ['body' => 'Yes we do!'])
        ->assertCreated();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && $request['recipient']['id'] === 'ig-user-1'
            && $request['message']['text'] === 'Yes we do!';
    });
});
