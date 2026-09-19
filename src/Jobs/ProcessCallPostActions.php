<?php

namespace Easyreply\Inbox\Jobs;

use Easyreply\Inbox\Events\CallUpdated;
use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Support\CallActionRunner;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCallPostActions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $provider,
        public readonly string $providerCallId,
        public readonly array $payload,
    ) {}

    public function handle(VoiceAgentManager $voices, CallActionRunner $actions): void
    {
        $driver = $voices->driver($this->provider);
        $data = $driver->normalizeInbound($this->payload);

        $call = CallLog::withoutGlobalScopes()->where('provider_call_id', $this->providerCallId)->first();

        if (! $call) {
            return;
        }

        $call->forceFill([
            'status' => $data->status,
            'transcript' => $data->transcript ?? $call->transcript,
            'summary' => $data->summary ?? $call->summary,
            'sentiment' => $data->sentiment ?? $call->sentiment,
            'duration_seconds' => $data->durationSeconds ?? $call->duration_seconds,
            'ended_at' => now(),
        ])->save();

        $actions->run($call);

        event(new CallUpdated($call));
    }
}
