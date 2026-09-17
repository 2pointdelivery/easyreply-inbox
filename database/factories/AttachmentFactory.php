<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\Attachment;
use Easyreply\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'disk' => 'local',
            'path' => 'shared-inbox/attachments/'.fake()->uuid().'.pdf',
            'filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
