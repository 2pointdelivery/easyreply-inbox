<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Team;
use Easyreply\Inbox\Models\VoiceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceSchedule>
 */
class VoiceScheduleFactory extends Factory
{
    protected $model = VoiceSchedule::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'to_e164' => '+1555'.fake()->unique()->numberBetween(1000000, 9999999),
            'scheduled_at' => now()->addHour(),
            'status' => VoiceSchedule::STATUS_PENDING,
        ];
    }
}
