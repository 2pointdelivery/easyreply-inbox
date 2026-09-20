<?php

use Easyreply\Inbox\Events\CallLogged;
use Easyreply\Inbox\Events\CallUpdated;
use Easyreply\Inbox\Jobs\DispatchVoiceSchedules;
use Easyreply\Inbox\Models\CallAction;
use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Models\VoiceSchedule;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'shared-inbox.voice.driver' => 'null',
        'shared-inbox.voice.shared_number_e164' => '+15550000000',
        'shared-inbox.voice.outbound.bulk' => true,
    ]);
});

it('creates a call log with linked contact, conversation, and message from a voice webhook', function () {
    Event::fake([CallLogged::class]);

    [$team] = createTeamWithAgent();
    $teamId = $team->id;

    $payload = [
        'call_id' => 'call-123',
        'from' => '+15551110001',
        'to' => '+15550000000',
        'direction' => 'inbound',
        'status' => 'completed',
        'transcript' => 'Hi, what are your hours?',
        'summary' => 'Asked about hours.',
        'duration_seconds' => 60,
    ];

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$teamId}", $payload)->assertNoContent();

    expect(CallLog::count())->toBe(1);

    $call = CallLog::first();
    expect($call->team_id)->toBe($teamId)
        ->and($call->transcript)->toBe('Hi, what are your hours?')
        ->and($call->contact_id)->not->toBeNull()
        ->and($call->conversation_id)->not->toBeNull();

    $conversation = Conversation::find($call->conversation_id);
    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->inbox->channel_type)->toBe('voice');

    expect($call->actions()->where('action_type', CallAction::TYPE_CREATE_THREAD)->count())->toBe(1)
        ->and($call->actions()->where('action_type', CallAction::TYPE_TRIAGE)->count())->toBe(1);

    Event::assertDispatched(CallLogged::class);
});

it('is idempotent on provider_call_id replays', function () {
    [$team] = createTeamWithAgent();

    $payload = ['call_id' => 'call-dedupe', 'from' => '+15551110002', 'to' => '+15550000000', 'transcript' => 'Hello'];

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", $payload)->assertNoContent();
    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", $payload)->assertNoContent();

    expect(CallLog::count())->toBe(1);
});

it('ignores webhook noise that is not worth processing', function () {
    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", ['ping' => true])->assertNoContent();

    expect(CallLog::count())->toBe(0);
});

it('rejects voice webhooks with an invalid signature when a secret is configured', function () {
    config(['shared-inbox.voice.generic_sip.webhook_secret' => 'shhh']);

    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/generic-sip?team_id={$team->id}", [
        'call_id' => 'call-x', 'from' => '+15551110003', 'transcript' => 'hi',
    ])->assertUnauthorized();

    expect(CallLog::count())->toBe(0);
});

it('404s unknown voice providers', function () {
    $this->postJson('/shared-inbox/webhooks/voice/nope', ['call_id' => 'x'])->assertNotFound();
});

it('never persists recording urls or audio blobs', function () {
    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", [
        'call_id' => 'call-scrub',
        'from' => '+15551110004',
        'transcript' => 'My card is 4111111111111111 thanks',
        'recording_url' => 'https://provider.example/audio.mp3',
        'audio' => 'BINARY-BLOB',
    ])->assertNoContent();

    $call = CallLog::first();
    expect($call->transcript)->not->toContain('4111111111111111')
        ->and($call->transcript)->toContain('[redacted-card]')
        ->and(json_encode($call->getAttributes()))->not->toContain('provider.example');

    // Strict schema: no audio columns exist at all.
    expect(\Illuminate\Support\Facades\Schema::hasColumn('call_logs', 'recording_url'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('call_logs', 'audio_path'))->toBeFalse();
});

it('maps angry sentiment to high priority triage', function () {
    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", [
        'call_id' => 'call-angry',
        'from' => '+15551110005',
        'transcript' => 'I am furious, this is urgent, fix it now',
        'sentiment' => 'negative',
    ])->assertNoContent();

    $call = CallLog::first();
    expect($call->priority_suggestion)->toBe('high');

    $conversation = Conversation::find($call->conversation_id);
    expect($conversation->priority)->toBe('high');
});

it('reuses the open voice conversation for a second call from the same caller', function () {
    [$team] = createTeamWithAgent();

    foreach (['call-a', 'call-b'] as $id) {
        $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", [
            'call_id' => $id, 'from' => '+15551110006', 'transcript' => 'hello',
        ])->assertNoContent();
    }

    expect(CallLog::count())->toBe(2)
        ->and(Conversation::count())->toBe(1)
        ->and(Contact::count())->toBe(1);
});

it('supports click-to-call outbound from a conversation', function () {
    Http::fake();

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->voice()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'voice', 'external_id' => '+15551110007']);
    $conversation = Conversation::factory()->for($team)->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id]);

    $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/click-to-call", ['to_e164' => '+15551110007'])
        ->assertCreated();

    $call = CallLog::first();
    expect($call->direction)->toBe(CallLog::DIRECTION_OUTBOUND)
        ->and($call->status)->toBe(CallLog::STATUS_RINGING);
});

it('live-transfers a call and logs the outcome', function () {
    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", [
        'call_id' => 'call-transfer', 'from' => '+15551110008', 'transcript' => 'get me a human',
    ])->assertNoContent();

    // Null driver transfer returns false -> transfer endpoint keeps status but records failure.
    $this->postJson('/shared-inbox/voice/null/transfer-callback', [
        'provider_call_id' => 'call-transfer',
        'transfer_target' => '+15552220000',
    ])->assertNoContent();

    $call = CallLog::first();
    expect($call->transfer_outcome)->toContain('transfer failed');
});

it('advances call status via status-callback and fires CallUpdated', function () {
    Event::fake([CallLogged::class, CallUpdated::class]);

    [$team] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$team->id}", [
        'call_id' => 'call-status', 'from' => '+15551110009', 'transcript' => 'hi',
    ])->assertNoContent();

    $this->postJson('/shared-inbox/voice/null/status-callback', [
        'call_id' => 'call-status',
        'from' => '+15551110009',
        'status' => 'completed',
        'summary' => 'Done.',
    ])->assertNoContent();

    expect(CallLog::first()->summary)->toBe('Done.');

    Event::assertDispatched(CallUpdated::class, fn (CallUpdated $e) => $e->broadcastAs() === 'CallUpdated');
});

it('dispatches due voice schedules as outbound calls within the rate limit', function () {
    [$team] = createTeamWithAgent();

    VoiceSchedule::factory()->for($team)->create(['to_e164' => '+15551110010', 'scheduled_at' => now()->subMinute()]);

    app(DispatchVoiceSchedules::class)->handle(app(VoiceAgentManager::class));

    expect(CallLog::count())->toBe(1)
        ->and(VoiceSchedule::first()->status)->toBe(VoiceSchedule::STATUS_SENT);
});

it('enforces cross-team isolation on call logs', function () {
    [$teamA] = createTeamWithAgent();
    [$teamB, $userB] = createTeamWithAgent();

    $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$teamA->id}", [
        'call_id' => 'call-team-a', 'from' => '+15551110011', 'transcript' => 'hi',
    ])->assertNoContent();

    $callA = CallLog::withoutGlobalScopes()->first();

    // Team B member sees no calls and cannot open team A's call page
    // (team-scoped route binding yields 404, never leaks existence).
    $this->actingAs($userB)->get('/shared-inbox/voice/calls')->assertOk();
    $this->actingAs($userB)->get("/shared-inbox/voice/calls/{$callA->id}")->assertNotFound();
});

it('allows team overrides of the global voice default', function () {
    [$team, $user] = createTeamWithAgent();
    $team->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $this->actingAs($user)->patch('/shared-inbox/settings/voice', [
        'voice_driver' => 'elevenlabs',
        'voice_prompt' => 'Answer as Acme support.',
        'transfer_target' => '+15553330000',
    ])->assertRedirect();

    expect($team->fresh()->voice_driver)->toBe('elevenlabs')
        ->and($team->fresh()->transfer_target)->toBe('+15553330000');
});

it('blocks non-admins from voice settings and schedules', function () {
    [$team, $user] = createTeamWithAgent();

    $this->actingAs($user)->patch('/shared-inbox/settings/voice', ['voice_driver' => 'elevenlabs'])->assertForbidden();

    $this->actingAs($user)->postJson('/shared-inbox/voice/schedules', [
        'to_e164' => '+15551110012', 'scheduled_at' => now()->addHour()->toISOString(),
    ])->assertForbidden();
});

it('purges transcripts past retention while keeping metadata', function () {
    [$team] = createTeamWithAgent();
    config(['shared-inbox.voice.retention_days' => 30]);

    $old = CallLog::factory()->create(['team_id' => $team->id, 'created_at' => now()->subDays(60)]);
    $fresh = CallLog::factory()->create(['team_id' => $team->id]);

    $this->artisan('shared-inbox:purge-voice-transcripts')->assertOk();

    expect($old->fresh()->transcript)->toBeNull()
        ->and($fresh->fresh()->transcript)->not->toBeNull()
        ->and(CallLog::count())->toBe(2);
});

it('links the same caller number to two different teams independently', function () {
    [$teamA] = createTeamWithAgent();
    [$teamB] = createTeamWithAgent();

    foreach ([$teamA->id, $teamB->id] as $i => $teamId) {
        $this->postJson("/shared-inbox/webhooks/voice/null?team_id={$teamId}", [
            'call_id' => "call-shared-{$i}",
            'from' => '+15551119999',
            'to' => '+15550000000',
            'transcript' => 'Hello from a shared number',
        ])->assertNoContent();
    }

    expect(CallLog::count())->toBe(2)
        ->and(Contact::count())->toBe(2)
        ->and(Conversation::count())->toBe(2);
});

it('exposes callable phones on the conversation page for click-to-call', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->voice()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'voice', 'external_id' => '+15551110013']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($user)
        ->get("/shared-inbox/conversations/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversation.contact.phones.0', '+15551110013'));
});
