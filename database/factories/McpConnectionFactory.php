<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\McpConnection;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpConnection>
 */
class McpConnectionFactory extends Factory
{
    protected $model = McpConnection::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'app_slug' => 'gmail',
            'state' => Str::random(40),
            'status' => McpConnection::STATUS_PENDING,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => McpConnection::STATUS_ACTIVE,
            'composio_connection_id' => 'ca_'.Str::random(12),
        ]);
    }
}
