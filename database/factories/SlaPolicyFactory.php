<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\SlaPolicy;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'priority' => 'normal',
            'first_response_minutes' => 60,
            'resolution_minutes' => 24 * 60,
        ];
    }
}
