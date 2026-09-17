<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Label;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
