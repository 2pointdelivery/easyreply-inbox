<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'shared-inbox.channels.whatsapp.enabled' => true,
        'shared-inbox.channels.whatsapp.app_secret' => null,
    ]);
});

it('completes the meta webhook verification handshake', function () {
    config(['shared-inbox.channels.whatsapp.verify_token' => 'verify-me']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();

    $response = $this->get("/shared-inbox/webhooks/whatsapp/{$inbox->id}?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=xyz");

    $response->assertOk();
    expect($response->getContent())->toBe('xyz');
});

it('rejects the verification handshake with the wrong token', function () {
    config(['shared-inbox.channels.whatsapp.verify_token' => 'verify-me']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();

    $this->get("/shared-inbox/webhooks/whatsapp/{$inbox->id}?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=xyz")
        ->assertForbidden();
});

it('creates a contact and conversation from an inbound whatsapp message', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'contacts' => [['profile' => ['name' => 'Casey Customer']]],
                    'messages' => [[
                        'from' => '15551234567',
                        'id' => 'wamid.ABC',
                        'text' => ['body' => 'Is my order ready?'],
                    ]],
                ],
            ]],
        ]],
    ];

    $this->postJson("/shared-inbox/webhooks/whatsapp/{$inbox->id}", $payload)->assertNoContent();

    expect(Contact::count())->toBe(1)
        ->and(Contact::first()->display_name)->toBe('Casey Customer')
        ->and(Conversation::count())->toBe(1);

    expect(Conversation::first()->messages()->first()->body)->toBe('Is my order ready?');
});

it('ignores whatsapp status-only callbacks', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();

    $payload = ['entry' => [['changes' => [['value' => ['statuses' => [['status' => 'delivered']]]]]]]];

    $this->postJson("/shared-inbox/webhooks/whatsapp/{$inbox->id}", $payload)->assertNoContent();

    expect(Conversation::count())->toBe(0);
});

it('reuses the open conversation for a second whatsapp message from the same contact', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();

    $payload = fn (string $text) => [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [['from' => '15551234567', 'id' => uniqid('wamid.'), 'text' => ['body' => $text]]],
                ],
            ]],
        ]],
    ];

    $this->postJson("/shared-inbox/webhooks/whatsapp/{$inbox->id}", $payload('First message'))->assertNoContent();
    $this->postJson("/shared-inbox/webhooks/whatsapp/{$inbox->id}", $payload('Second message'))->assertNoContent();

    expect(Conversation::count())->toBe(1)
        ->and(Conversation::first()->messages()->count())->toBe(2);
});

it('sends an outbound whatsapp reply', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->whatsapp()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'whatsapp', 'external_id' => '15551234567']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $user = createTestUser();
    $team->users()->attach($user->id, ['role' => 'agent']);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", ['body' => 'Yes, shipped today!'])
        ->assertCreated();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && $request['to'] === '15551234567'
            && $request['text']['body'] === 'Yes, shipped today!';
    });
});
