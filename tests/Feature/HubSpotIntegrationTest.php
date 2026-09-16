<?php

use Easyreply\Inbox\Integrations\HubSpotIntegration;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

it('fetches crm context for a contact by email', function () {
    Http::fake([
        'https://api.hubapi.com/*' => Http::response([
            'properties' => ['company' => 'Acme Inc', 'lifecyclestage' => 'customer'],
        ]),
    ]);

    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'casey@acme.test']);

    $integration = new HubSpotIntegration(['api_key' => 'hs-test']);

    $context = $integration->fetchCustomerContext($contact);

    expect($context)->toBe(['company' => 'Acme Inc', 'lifecyclestage' => 'customer']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'casey%40acme.test') || str_contains($request->url(), 'casey@acme.test'));
});

it('returns empty context without an api key or email identity', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();

    $integration = new HubSpotIntegration([]);

    expect($integration->fetchCustomerContext($contact))->toBe([]);
});

it('has no-op issue linking and incident status', function () {
    $team = Team::factory()->create();
    $conversation = Conversation::factory()->for($team)->create();

    $integration = new HubSpotIntegration(['api_key' => 'x']);

    expect($integration->linkExternalIssue($conversation, []))->toBeNull()
        ->and($integration->fetchIncidentStatus())->toBe([]);
});
