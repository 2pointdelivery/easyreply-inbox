<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    protected $model = CallLog::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'direction' => CallLog::DIRECTION_INBOUND,
            'from_e164' => '+1555'.fake()->unique()->numberBetween(1000000, 9999999),
            'to_e164' => '+15550000000',
            'provider' => 'null',
            'provider_call_id' => (string) Str::uuid(),
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Caller asked about opening hours.',
            'summary' => 'Asked about hours; told 9-5.',
            'sentiment' => 'neutral',
        ];
    }
}
