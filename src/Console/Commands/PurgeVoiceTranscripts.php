<?php

namespace Easyreply\Inbox\Console\Commands;

use Easyreply\Inbox\Models\CallLog;
use Illuminate\Console\Command;

class PurgeVoiceTranscripts extends Command
{
    public $signature = 'shared-inbox:purge-voice-transcripts';

    public $description = 'Purge voice transcripts/summaries past the retention window (metadata + audit rows are kept).';

    public function handle(): int
    {
        $days = (int) config('shared-inbox.voice.retention_days', 90);
        $cutoff = now()->subDays($days);

        $count = CallLog::query()
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('transcript')->orWhereNotNull('summary')->orWhereNotNull('raw_payload'))
            ->update(['transcript' => null, 'summary' => null, 'raw_payload' => null]);

        $this->info("Purged transcripts for {$count} call(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
