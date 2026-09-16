<?php

use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

it('routes a new conversation to slack when routing is enabled', function () {
    config([
        'shared-inbox.channels.email.webhook_secret' => null,
        'shared-inbox.slack_routing.enabled' => true,
        'shared-inbox.slack_routing.bot_token' => 'xoxb-routing-token',
        'shared-inbox.slack_routing.channel' => '#support',
    ]);

    Http::fake(['https://slack.com/*' => Http::response(['ok' => true])]);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create(['name' => 'Support Inbox']);

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", [
        'FromFull' => ['Email' => 'customer@example.com', 'Name' => 'Casey Customer'],
        'Subject' => 'Help with my order',
        'TextBody' => 'Where is my order?',
        'MessageID' => 'msg-1',
        'Headers' => [],
    ])->assertNoContent();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === '#support'
            && str_contains($request['text'], 'Help with my order');
    });
});

it('does not call slack when routing is disabled', function () {
    config([
        'shared-inbox.channels.email.webhook_secret' => null,
        'shared-inbox.slack_routing.enabled' => false,
    ]);

    Http::fake();

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create();

    $this->postJson("/shared-inbox/webhooks/email/{$inbox->id}", [
        'FromFull' => ['Email' => 'customer@example.com', 'Name' => 'Casey Customer'],
        'Subject' => 'Help with my order',
        'TextBody' => 'Where is my order?',
        'MessageID' => 'msg-1',
        'Headers' => [],
    ])->assertNoContent();

    Http::assertNothingSent();
});
