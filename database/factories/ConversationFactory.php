<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'inbox_id' => Inbox::factory(),
            'contact_id' => Contact::factory(),
            'subject' => fake()->sentence(),
            'status' => 'open',
            'priority' => 'normal',
        ];
    }
}
