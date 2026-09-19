<?php

use Easyreply\Inbox\Events\MessageReceived;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Models\VoiceSchedule;
use Illuminate\Support\Facades\Event;

function widgetInbox(?Team $team = null): array
{
    $team ??= Team::factory()->create();

    $inbox = Inbox::factory()->for($team)->widget()->create();

    return [$team, $inbox, $inbox->config['widget_token']];
}

it('serves the embeddable widget loader as javascript', function () {
    $response = $this->get('/shared-inbox/widget.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and($response->getContent())->toContain('easyreply-widget-host');
});

it('renders the iframe page with a valid token and refuses a bad one', function () {
    [$team, $inbox, $token] = widgetInbox();

    $this->get("/shared-inbox/widget?inbox={$inbox->id}&token={$token}")->assertOk();
    $this->get("/shared-inbox/widget?inbox={$inbox->id}&token=wrong")->assertForbidden();
});

it('starts a guest chat session without an account', function () {
    [$team, $inbox, $token] = widgetInbox();

    $response = $this->postJson("/shared-inbox/widget/{$inbox->id}/start", [
        'token' => $token,
        'name' => 'Wendy Visitor',
    ])->assertCreated();

    expect($response->json('visitor_token'))->not->toBeEmpty()
        ->and(Conversation::count())->toBe(1);

    $conversation = Conversation::first();
    expect($conversation->inbox->channel_type)->toBe('widget')
        ->and($response->json('conversation_id'))->toBe($conversation->id);
});

it('rejects chat start with a bad token or a disabled widget', function () {
    [$team, $inbox, $token] = widgetInbox();

    $this->postJson("/shared-inbox/widget/{$inbox->id}/start", ['token' => 'nope'])->assertUnauthorized();

    $config = $inbox->config;
    $config['widget_enabled'] = false;
    $inbox->forceFill(['config' => $config])->save();

    $this->postJson("/shared-inbox/widget/{$inbox->id}/start", ['token' => $token])->assertUnauthorized();

    expect(Conversation::count())->toBe(0);
});

it('carries a full guest-to-agent chat roundtrip', function () {
    Event::fake([MessageReceived::class]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->widget()->create();
    $token = $inbox->config['widget_token'];

    $start = $this->postJson("/shared-inbox/widget/{$inbox->id}/start", [
        'token' => $token, 'name' => 'Wendy',
    ])->assertCreated()->json();

    $this->postJson("/shared-inbox/widget/{$inbox->id}/messages", [
        'token' => $token,
        'visitor_token' => $start['visitor_token'],
        'body' => 'Do you ship abroad?',
    ])->assertCreated();

    Event::assertDispatched(MessageReceived::class);

    // Agent replies from the team inbox; the guest sees it on next poll.
    $conversation = Conversation::first();

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/messages", ['body' => 'Yes, worldwide!'])
        ->assertCreated();

    $poll = $this->getJson("/shared-inbox/widget/{$inbox->id}/messages?token={$token}&visitor_token={$start['visitor_token']}")->assertOk()->json();

    expect(collect($poll['messages'])->pluck('body')->all())
        ->toContain('Do you ship abroad?', 'Yes, worldwide!');

    $after = $this->getJson("/shared-inbox/widget/{$inbox->id}/messages?token={$token}&visitor_token={$start['visitor_token']}&after_id=1")->assertOk()->json();

    expect($after['messages'])->toHaveCount(1);
});

it('rejects guest messages with an unknown visitor token', function () {
    [$team, $inbox, $token] = widgetInbox();

    $this->postJson("/shared-inbox/widget/{$inbox->id}/messages", [
        'token' => $token, 'visitor_token' => 'unknown', 'body' => 'hi',
    ])->assertUnauthorized();
});

it('isolates widget tokens across inboxes', function () {
    [$teamA, $inboxA, $tokenA] = widgetInbox();
    [$teamB, $inboxB, $tokenB] = widgetInbox();

    $this->postJson("/shared-inbox/widget/{$inboxA->id}/start", ['token' => $tokenB])->assertUnauthorized();
    $this->postJson("/shared-inbox/widget/{$inboxB->id}/start", ['token' => $tokenA])->assertUnauthorized();

    expect(Conversation::count())->toBe(0);
});

it('queues a callback request with a valid phone number', function () {
    [$team, $inbox, $token] = widgetInbox();

    $start = $this->postJson("/shared-inbox/widget/{$inbox->id}/start", ['token' => $token])->assertCreated()->json();

    $response = $this->postJson("/shared-inbox/widget/{$inbox->id}/call-request", [
        'token' => $token,
        'visitor_token' => $start['visitor_token'],
        'phone' => '+15551110001',
        'topic' => 'Billing question',
    ])->assertCreated();

    expect($response->json('status'))->toBe('scheduled');

    $schedule = VoiceSchedule::first();
    expect($schedule->to_e164)->toBe('+15551110001')
        ->and($schedule->meta['topic'])->toBe('Billing question')
        ->and($schedule->meta['source'])->toBe('widget')
        ->and($schedule->conversation_id)->toBe($start['conversation_id']);
});

it('validates callback phone numbers and honors the callbacks flag', function () {
    [$team, $inbox, $token] = widgetInbox();

    $start = $this->postJson("/shared-inbox/widget/{$inbox->id}/start", ['token' => $token])->assertCreated()->json();

    $this->postJson("/shared-inbox/widget/{$inbox->id}/call-request", [
        'token' => $token,
        'visitor_token' => $start['visitor_token'],
        'phone' => 'not-a-number',
    ])->assertUnprocessable();

    config(['shared-inbox.voice.outbound.widget_callbacks' => false]);

    $this->postJson("/shared-inbox/widget/{$inbox->id}/call-request", [
        'token' => $token,
        'visitor_token' => $start['visitor_token'],
        'phone' => '+15551110001',
    ])->assertForbidden();

    expect(VoiceSchedule::count())->toBe(0);
});

it('gates widget settings behind team admins', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->widget()->create();

    $this->actingAs($user)->get('/shared-inbox/settings/widget')->assertOk();
    $this->actingAs($user)->patch("/shared-inbox/settings/widget/{$inbox->id}", ['widget_enabled' => false])->assertForbidden();

    $team->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $oldToken = $inbox->config['widget_token'];

    $this->actingAs($user)->patch("/shared-inbox/settings/widget/{$inbox->id}", ['widget_enabled' => false])->assertRedirect();
    expect($inbox->fresh()->config['widget_enabled'])->toBeFalse();

    $this->actingAs($user)->post("/shared-inbox/settings/widget/{$inbox->id}/regenerate")->assertRedirect();
    expect($inbox->fresh()->config['widget_token'])->not->toBe($oldToken);
});
