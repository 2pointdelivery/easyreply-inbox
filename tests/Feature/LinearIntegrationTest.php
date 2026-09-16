<?php

use Easyreply\Inbox\Integrations\LinearIntegration;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

it('creates a linear issue and returns an external link', function () {
    Http::fake([
        'https://api.linear.app/graphql' => Http::response([
            'data' => ['issueCreate' => [
                'success' => true,
                'issue' => ['id' => 'issue-1', 'url' => 'https://linear.app/team/issue/ISS-1'],
            ]],
        ]),
    ]);

    $team = Team::factory()->create();
    $inbox = Inbox::factory()->for($team)->email()->create();
    $contact = Contact::factory()->for($team)->create();
    $conversation = Conversation::factory()->for($team)->create([
        'inbox_id' => $inbox->id,
        'contact_id' => $contact->id,
        'subject' => 'Bug: export button broken',
    ]);

    $integration = new LinearIntegration(['api_key' => 'lin_test', 'team_id' => 'linear-team-1']);

    $link = $integration->linkExternalIssue($conversation, []);

    expect($link)->not->toBeNull()
        ->and($link->provider)->toBe('linear')
        ->and($link->externalId)->toBe('issue-1')
        ->and($link->url)->toBe('https://linear.app/team/issue/ISS-1');

    Http::assertSent(fn ($request) => str_contains($request['variables']['input']['title'] ?? '', 'export button broken'));
});

it('returns null without credentials configured', function () {
    $team = Team::factory()->create();
    $conversation = Conversation::factory()->for($team)->create();

    $integration = new LinearIntegration([]);

    expect($integration->linkExternalIssue($conversation, []))->toBeNull();
});

it('has no-op crm context and incident status', function () {
    $contact = new Contact;
    $integration = new LinearIntegration(['api_key' => 'x', 'team_id' => 'y']);

    expect($integration->fetchCustomerContext($contact))->toBe([])
        ->and($integration->fetchIncidentStatus())->toBe([]);
});
