<?php

use Easyreply\Inbox\Integrations\HubSpotIntegration;
use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\IntegrationSetting;
use Easyreply\Inbox\Models\Team;

it('returns null when the integration is not enabled at the config level', function () {
    config(['shared-inbox.integrations.hubspot.enabled' => false]);

    $team = Team::factory()->create();
    IntegrationSetting::factory()->for($team)->create(['integration_key' => 'hubspot', 'enabled' => true]);

    expect(app(IntegrationManager::class)->for($team, 'hubspot'))->toBeNull();
});

it('returns null when the team has not enabled the integration', function () {
    config(['shared-inbox.integrations.hubspot.enabled' => true]);

    $team = Team::factory()->create();
    IntegrationSetting::factory()->for($team)->create(['integration_key' => 'hubspot', 'enabled' => false]);

    expect(app(IntegrationManager::class)->for($team, 'hubspot'))->toBeNull();
});

it('returns null when the team has no setting row at all', function () {
    config(['shared-inbox.integrations.hubspot.enabled' => true]);

    $team = Team::factory()->create();

    expect(app(IntegrationManager::class)->for($team, 'hubspot'))->toBeNull();
});

it('resolves the driver with the team\'s settings when both are enabled', function () {
    config(['shared-inbox.integrations.hubspot.enabled' => true]);

    $team = Team::factory()->create();
    IntegrationSetting::factory()->for($team)->create([
        'integration_key' => 'hubspot',
        'enabled' => true,
        'config' => ['api_key' => 'hs-test-key'],
    ]);

    $integration = app(IntegrationManager::class)->for($team, 'hubspot');

    expect($integration)->toBeInstanceOf(HubSpotIntegration::class);
});
