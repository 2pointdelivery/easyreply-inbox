<?php

use Easyreply\Inbox\Integrations\BetterstackIntegration;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

it('returns only unresolved incidents', function () {
    Http::fake([
        'https://uptime.betterstack.com/*' => Http::response([
            'data' => [
                ['id' => '1', 'attributes' => ['name' => 'API outage', 'resolved_at' => null]],
                ['id' => '2', 'attributes' => ['name' => 'Old incident', 'resolved_at' => '2024-01-01T00:00:00Z']],
            ],
        ]),
    ]);

    $integration = new BetterstackIntegration(['api_key' => 'bs-test']);

    $incidents = $integration->fetchIncidentStatus();

    expect($incidents)->toHaveCount(1)
        ->and($incidents[0]['attributes']['name'])->toBe('API outage');
});

it('returns no incidents without an api key', function () {
    $integration = new BetterstackIntegration([]);

    expect($integration->fetchIncidentStatus())->toBe([]);
});

it('has no-op issue linking and customer context', function () {
    $team = Team::factory()->create();
    $conversation = Conversation::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();

    $integration = new BetterstackIntegration(['api_key' => 'x']);

    expect($integration->linkExternalIssue($conversation, []))->toBeNull()
        ->and($integration->fetchCustomerContext($contact))->toBe([]);
});
