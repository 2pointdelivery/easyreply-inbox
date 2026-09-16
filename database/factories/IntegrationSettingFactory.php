<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\IntegrationSetting;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationSetting>
 */
class IntegrationSettingFactory extends Factory
{
    protected $model = IntegrationSetting::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'integration_key' => 'linear',
            'enabled' => true,
            'config' => ['api_key' => 'test-key'],
        ];
    }
}
