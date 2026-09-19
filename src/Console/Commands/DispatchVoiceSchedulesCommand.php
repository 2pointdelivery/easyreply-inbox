<?php

namespace Easyreply\Inbox\Console\Commands;

use Easyreply\Inbox\Jobs\DispatchVoiceSchedules;
use Illuminate\Console\Command;

class DispatchVoiceSchedulesCommand extends Command
{
    public $signature = 'shared-inbox:dispatch-voice-schedules';

    public $description = 'Dial due voice callbacks (widget call requests + scheduled follow-ups), honoring the per-minute rate limit.';

    public function handle(): int
    {
        DispatchVoiceSchedules::dispatch();

        $this->info('Voice schedule dispatch queued.');

        return self::SUCCESS;
    }
}
