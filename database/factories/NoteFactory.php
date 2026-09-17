<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            // No generic factory for the host app's User model (see
            // Tests/Fixtures/User) — callers override this with a real id.
            'user_id' => 1,
            'body' => fake()->sentence(),
            'mentioned_user_ids' => [],
        ];
    }
}
