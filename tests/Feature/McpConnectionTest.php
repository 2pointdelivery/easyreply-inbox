<?php

use Easyreply\Inbox\Models\McpConnection;
use Easyreply\Inbox\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['shared-inbox.mcp.composio_api_key' => 'test-composio-key']);
});

it('lists available composio apps and the team\'s connections', function () {
    Http::fake([
        'https://backend.composio.dev/api/v1/apps' => Http::response([
            'items' => [['key' => 'gmail', 'name' => 'Gmail'], ['key' => 'linear', 'name' => 'Linear']],
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();
    McpConnection::factory()->for($team)->active()->create(['app_slug' => 'gmail']);

    $response = $this->actingAs($user)->get('/shared-inbox/settings/mcp');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Mcp')
        ->has('apps', 2)
        ->has('connections', 1)
        ->where('connections.0.app_slug', 'gmail')
        ->where('connections.0.status', 'active')
    );
});

it('initiates a connection and redirects to composio\'s hosted oauth flow', function () {
    Http::fake([
        'https://backend.composio.dev/api/v1/connectedAccounts' => Http::response([
            'connectedAccountId' => 'ca_123',
            'redirectUrl' => 'https://backend.composio.dev/oauth/authorize?token=abc',
        ]),
    ]);

    [$team, $user] = createTeamWithAgent();

    $response = $this->actingAs($user)->post('/shared-inbox/settings/mcp/gmail/connect');

    $response->assertRedirect('https://backend.composio.dev/oauth/authorize?token=abc');

    expect(McpConnection::count())->toBe(1);
    $connection = McpConnection::first();
    expect($connection->team_id)->toBe($team->id)
        ->and($connection->app_slug)->toBe('gmail')
        ->and($connection->composio_connection_id)->toBe('ca_123')
        ->and($connection->status)->toBe('pending');
});

it('completes a connection when composio redirects back with a valid state', function () {
    [$team, $user] = createTeamWithAgent();
    $connection = McpConnection::factory()->for($team)->create([
        'app_slug' => 'gmail',
        'composio_connection_id' => 'ca_123',
        'state' => 'valid-state-token',
        'status' => 'pending',
    ]);

    Http::fake([
        'https://backend.composio.dev/api/v1/connectedAccounts/ca_123' => Http::response([
            'status' => 'ACTIVE',
            'scopes' => ['gmail.readonly'],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->get('/shared-inbox/mcp/callback?state=valid-state-token');

    $response->assertRedirect('/shared-inbox/settings/mcp');

    $connection->refresh();
    expect($connection->status)->toBe('active')
        ->and($connection->scopes)->toBe(['gmail.readonly']);
});

it('rejects a callback with an unknown state', function () {
    [$team, $user] = createTeamWithAgent();

    $response = $this->actingAs($user)
        ->get('/shared-inbox/mcp/callback?state=does-not-exist');

    $response->assertRedirect('/shared-inbox/settings/mcp');
    $response->assertSessionHasErrors('state');
});

it('disconnects a connection', function () {
    Http::fake(['https://backend.composio.dev/api/v1/connectedAccounts/*' => Http::response([])]);

    [$team, $user] = createTeamWithAgent();
    $connection = McpConnection::factory()->for($team)->active()->create();

    $this->actingAs($user)
        ->delete("/shared-inbox/settings/mcp/{$connection->id}")
        ->assertRedirect();

    expect($connection->refresh()->status)->toBe('revoked');
});

it('does not let a user from another team disconnect a connection', function () {
    Http::fake();

    $otherTeam = Team::factory()->create();
    $connection = McpConnection::factory()->for($otherTeam)->active()->create();

    [$team, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->delete("/shared-inbox/settings/mcp/{$connection->id}")
        ->assertNotFound();

    expect($connection->refresh()->status)->toBe('active');
});
