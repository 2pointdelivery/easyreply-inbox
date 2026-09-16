<?php

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;

it('links a conversation to a new linear issue', function () {
    config(['shared-inbox.integrations.linear.enabled' => true]);

    Http::fake([
        'https://api.linear.app/graphql' => Http::response([
            'data' => ['issueCreate' => [
                'success' => true,
                'issue' => ['id' => 'issue-1', 'url' => 'https://linear.app/team/issue/ISS-1'],
            ]],
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();
    IntegrationSetting::factory()->for($team)->create([
        'integration_key' => 'linear',
        'enabled' => true,
        'config' => ['api_key' => 'lin_test', 'team_id' => 'linear-team-1'],
    ]);

    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/link", ['title' => 'Recurring export bug']);

    $response->assertCreated();
    expect($response->json('link.provider'))->toBe('linear')
        ->and($response->json('link.url'))->toBe('https://linear.app/team/issue/ISS-1');
});

it('refuses to link when linear is not configured for the team', function () {
    config(['shared-inbox.integrations.linear.enabled' => true]);

    [$team, $user] = createTeamWithAgent();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/shared-inbox/conversations/{$conversation->id}/link", []);

    $response->assertStatus(422);
    expect($response->json('link'))->toBeNull();
});
