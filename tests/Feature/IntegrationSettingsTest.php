<?php

use Easyreply\Inbox\Models\IntegrationSetting;

beforeEach(function () {
    config([
        'shared-inbox.integrations.linear.enabled' => true,
        'shared-inbox.integrations.hubspot.enabled' => true,
        'shared-inbox.integrations.betterstack.enabled' => true,
    ]);
});

it('lists the available integrations and the team\'s current settings', function () {
    [$team, $user] = createTeamWithAgent();
    IntegrationSetting::factory()->for($team)->create([
        'integration_key' => 'linear',
        'enabled' => true,
        'config' => ['api_key' => 'lin_test'],
    ]);

    $response = $this->actingAs($user)->get('/shared-inbox/settings/integrations');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Integrations')
        ->has('integrations', 3)
        ->where('integrations.0.key', 'linear')
        ->where('integrations.0.enabled', true)
        ->where('integrations.0.configured', true)
        ->where('integrations.1.enabled', false)
    );
});

it('enables an integration for the team', function () {
    [$team, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->patch('/shared-inbox/settings/integrations/hubspot', ['enabled' => true])
        ->assertRedirect();

    $setting = IntegrationSetting::where('team_id', $team->id)->where('integration_key', 'hubspot')->first();
    expect($setting)->not->toBeNull()
        ->and($setting->enabled)->toBeTrue();
});

it('rejects updating an unknown integration key', function () {
    [$team, $user] = createTeamWithAgent();

    $this->actingAs($user)
        ->patch('/shared-inbox/settings/integrations/not-a-real-integration', ['enabled' => true])
        ->assertNotFound();
});
