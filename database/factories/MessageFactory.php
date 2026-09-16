<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'direction' => Message::DIRECTION_INBOUND,
            'channel' => 'email',
            'body' => fake()->paragraph(),
            'status' => Message::STATUS_SENT,
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => ['direction' => Message::DIRECTION_OUTBOUND]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Message::STATUS_DRAFT,
            'ai_generated' => true,
        ]);
    }
}
