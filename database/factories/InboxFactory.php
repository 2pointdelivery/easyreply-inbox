<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Inbox>
 */
class InboxFactory extends Factory
{
    protected $model = Inbox::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'channel_type' => 'email',
            'name' => fake()->unique()->safeEmail(),
            'config' => ['address' => fake()->unique()->safeEmail()],
            'is_active' => true,
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => ['channel_type' => 'email']);
    }

    public function slack(): static
    {
        return $this->state(fn () => [
            'channel_type' => 'slack',
            'name' => 'Slack',
            'config' => ['bot_token' => 'xoxb-test-token'],
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'channel_type' => 'whatsapp',
            'name' => 'WhatsApp',
            'config' => ['access_token' => 'test-token', 'phone_number_id' => '1234567890'],
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn () => [
            'channel_type' => 'instagram',
            'name' => 'Instagram',
            'config' => ['access_token' => 'test-token', 'page_id' => '1234567890'],
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn () => [
            'channel_type' => 'voice',
            'name' => 'Voice',
            'config' => ['number' => '+15550000000'],
        ]);
    }

    public function widget(): static
    {
        return $this->state(fn () => [
            'channel_type' => 'widget',
            'name' => 'Website',
            'config' => ['widget_enabled' => true, 'widget_token' => Str::random(40)],
        ]);
    }
}
