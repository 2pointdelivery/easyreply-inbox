<?php

namespace Easyreply\Inbox\Jobs;

use Easyreply\Inbox\Events\CallLogged;
use Easyreply\Inbox\Models\VoiceAgent;
use Easyreply\Inbox\Support\CallActionRunner;
use Easyreply\Inbox\Support\CallLinker;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundCall implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
        public readonly ?int $teamId = null,
    ) {}

    public function handle(VoiceAgentManager $voices, CallLinker $linker, CallActionRunner $actions): void
    {
        $driver = $voices->driver($this->provider);
        $data = $driver->normalizeInbound($this->payload);

        $agent = VoiceAgent::withoutGlobalScopes()
            ->where('driver', $this->provider)
            ->where('is_active', true)
            ->when($this->teamId, fn ($q) => $q->where(fn ($qq) => $qq->where('team_id', $this->teamId)->orWhereNull('team_id')))
            ->first();

        $call = $linker->link($data, $this->teamId ?? $agent?->team_id, $agent);
        $actions->run($call);

        event(new CallLogged($call));
    }
}
