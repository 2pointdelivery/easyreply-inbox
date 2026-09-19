<?php

namespace Easyreply\Inbox\Events;

use Easyreply\Inbox\Models\CallLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallLogged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly CallLog $callLog,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shared-inbox.team.{$this->callLog->team_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallLogged';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => [
                'id' => $this->callLog->id,
                'direction' => $this->callLog->direction,
                'status' => $this->callLog->status,
                'from_e164' => $this->callLog->from_e164,
                'conversation_id' => $this->callLog->conversation_id,
            ],
        ];
    }
}
