<?php

namespace Easyreply\Inbox\Database\Factories;

use Easyreply\Inbox\Models\CallAction;
use Easyreply\Inbox\Models\CallLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallAction>
 */
class CallActionFactory extends Factory
{
    protected $model = CallAction::class;

    public function definition(): array
    {
        return [
            'call_log_id' => CallLog::factory(),
            'action_type' => CallAction::TYPE_CREATE_THREAD,
            'payload' => [],
            'status' => CallAction::STATUS_DONE,
        ];
    }
}
