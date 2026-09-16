<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'shared-inbox.channels.slack.enabled' => true,
        'shared-inbox.channels.slack.signing_secret' => null,
    ]);
});

it('responds to the slack url_verification handshake without processing it as a message', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->slack()->create();

    $response = $this->postJson("/shared-inbox/webhooks/slack/{$inbox->id}", [
        'type' => 'url_verification',
        'challenge' => 'abc123',
    ]);

    $response->assertOk();
    expect($response->getContent())->toBe('abc123')
        ->and(Conversation::count())->toBe(0);
});

it('creates a contact and conversation from a slack message event', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->slack()->create();

    $payload = [
        'type' => 'event_callback',
        'event' => [
            'type' => 'message',
            'user' => 'U123',
            'text' => 'Need help please',
            'ts' => '1690000000.000100',
        ],
    ];

    $this->postJson("/shared-inbox/webhooks/slack/{$inbox->id}", $payload)->assertNoContent();

    expect(Contact::count())->toBe(1)
        ->and(Conversation::count())->toBe(1);

    $conversation = Conversation::first();
    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->messages()->first()->body)->toBe('Need help please');
});

it('ignores the bot channel account own echoed messages', function () {
    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->slack()->create();

    $payload = [
        'type' => 'event_callback',
        'event' => [
            'type' => 'message',
            'user' => 'U123',
            'text' => 'hi',
            'ts' => '1.1',
            'bot_id' => 'B999',
        ],
    ];

    $this->postJson("/shared-inbox/webhooks/slack/{$inbox->id}", $payload)->assertNoContent();

    expect(Conversation::count())->toBe(0);
});

it('rejects a slack webhook missing a valid signature when a signing secret is configured', function () {
    config(['shared-inbox.channels.slack.signing_secret' => 'shhh']);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->slack()->create();

    $this->postJson("/shared-inbox/webhooks/slack/{$inbox->id}", [
        'type' => 'event_callback',
        'event' => ['type' => 'message', 'user' => 'U123', 'text' => 'hi', 'ts' => '1.1'],
    ])->assertUnauthorized();

    expect(Conversation::count())->toBe(0);
});

it('sends an outbound slack reply via chat.postMessage', function () {
    Http::fake(['https://slack.com/*' => Http::response(['ok' => true])]);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->slack()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'slack', 'external_id' => 'U123']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $user = createTestUser();
    $team->users()->attach($user->id, ['role' => 'agent']);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", ['body' => 'On it!'])
        ->assertCreated();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'U123'
            && $request['text'] === 'On it!';
    });
});
