<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;

it('includes hubspot context and betterstack incidents when both are enabled', function () {
    config([
        'shared-inbox.integrations.hubspot.enabled' => true,
        'shared-inbox.integrations.betterstack.enabled' => true,
    ]);

    Http::fake([
        'https://api.hubapi.com/*' => Http::response(['properties' => ['company' => 'Acme Inc']]),
        'https://uptime.betterstack.com/*' => Http::response([
            'data' => [['id' => '1', 'attributes' => ['name' => 'API outage', 'resolved_at' => null]]],
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();
    IntegrationSetting::factory()->for($team)->create([
        'integration_key' => 'hubspot',
        'enabled' => true,
        'config' => ['api_key' => 'hs-test'],
    ]);
    IntegrationSetting::factory()->for($team)->create([
        'integration_key' => 'betterstack',
        'enabled' => true,
        'config' => ['api_key' => 'bs-test'],
    ]);

    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->channelIdentities()->create(['channel_type' => 'email', 'external_id' => 'casey@acme.test']);
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $response = $this->actingAs($user)->get("/shared-inbox/conversations/{$conversation->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Show')
        ->where('sidebar.customer_context.company', 'Acme Inc')
        ->has('sidebar.incidents', 1)
    );
});

it('has empty sidebar data when no integrations are enabled', function () {
    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $response = $this->actingAs($user)->get("/shared-inbox/conversations/{$conversation->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Show')
        ->where('sidebar.customer_context', [])
        ->where('sidebar.incidents', [])
    );
});
