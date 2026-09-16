<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

function makeConversation(Team $team): Conversation
{
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'customer@example.com']);

    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $conversation->messages()->create([
        'direction' => Message::DIRECTION_INBOUND,
        'channel' => 'email',
        'sender_type' => Contact::class,
        'sender_id' => $contact->id,
        'body' => 'Where is my order?',
        'status' => Message::STATUS_SENT,
    ]);

    return $conversation;
}

it('returns no draft when the ai driver is not configured (default)', function () {
    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/ai-draft");

    $response->assertOk()->assertJson(['draft' => null]);
    expect(Message::where('status', Message::STATUS_DRAFT)->count())->toBe(0);
});

it('creates a draft message from the openai reference driver', function () {
    config([
        'shared-inbox.ai.driver' => 'openai',
        'shared-inbox.ai.openai.api_key' => 'test-key',
    ]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Your order ships tomorrow!']]],
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/ai-draft");

    $response->assertCreated();
    expect($response->json('draft.body'))->toBe('Your order ships tomorrow!')
        ->and($response->json('draft.ai_generated'))->toBeTrue()
        ->and($response->json('draft.status'))->toBe(Message::STATUS_DRAFT);
});

it('creates a draft message from the anthropic reference driver', function () {
    config([
        'shared-inbox.ai.driver' => 'anthropic',
        'shared-inbox.ai.anthropic.api_key' => 'test-key',
    ]);

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'On it — shipping today!']],
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/ai-draft");

    $response->assertCreated();
    expect($response->json('draft.body'))->toBe('On it — shipping today!');
});

it('returns no draft when the configured provider call fails', function () {
    config([
        'shared-inbox.ai.driver' => 'openai',
        'shared-inbox.ai.openai.api_key' => 'test-key',
    ]);

    Http::fake(['https://api.openai.com/*' => Http::response(['error' => 'boom'], 500)]);

    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/ai-draft");

    $response->assertOk()->assertJson(['draft' => null]);
});

it('finalizes and sends a draft, updating the same message row', function () {
    config(['shared-inbox.channels.email.webhook_secret' => null]);
    Illuminate\Support\Facades\Mail::fake();

    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $draft = $conversation->messages()->create([
        'direction' => Message::DIRECTION_OUTBOUND,
        'channel' => 'email',
        'body' => 'Draft reply text',
        'ai_generated' => true,
        'status' => Message::STATUS_DRAFT,
    ]);

    $response = $this->actingAs($user)
        ->patchJson("/shared-inbox/messages/{$draft->id}/send", ['body' => 'Edited reply text']);

    $response->assertOk();

    $draft->refresh();
    expect($draft->status)->toBe(Message::STATUS_SENT)
        ->and($draft->body)->toBe('Edited reply text')
        ->and(Message::count())->toBe(2); // the original inbound + this one, no extra row created

    Illuminate\Support\Facades\Mail::assertSentCount(1);
});

it('refuses to send a draft that was already sent', function () {
    [$team, $user] = createTeamWithAgent();
    $conversation = makeConversation($team);

    $sent = $conversation->messages()->create([
        'direction' => Message::DIRECTION_OUTBOUND,
        'channel' => 'email',
        'body' => 'Already sent',
        'status' => Message::STATUS_SENT,
    ]);

    $this->actingAs($user)
        ->patchJson("/shared-inbox/messages/{$sent->id}/send", ['body' => 'Trying again'])
        ->assertStatus(422);
});
