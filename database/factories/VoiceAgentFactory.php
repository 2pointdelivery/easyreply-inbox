<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Models\VoiceAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceAgent>
 */
class VoiceAgentFactory extends Factory
{
    protected $model = VoiceAgent::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Receptionist',
            'driver' => 'null',
            'prompt' => 'You are a helpful receptionist.',
            'is_active' => true,
        ];
    }
}
