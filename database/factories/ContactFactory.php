<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'display_name' => fake()->name(),
        ];
    }
}
