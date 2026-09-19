<?php

namespace Easyreply\Inbox\Events;

use Easyreply\Inbox\Models\CallLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallUpdated implements ShouldBroadcast
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
        $channels = [
            new PrivateChannel("shared-inbox.team.{$this->callLog->team_id}"),
        ];

        if ($this->callLog->conversation_id) {
            $channels[] = new PrivateChannel("shared-inbox.conversation.{$this->callLog->conversation_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'CallUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => [
                'id' => $this->callLog->id,
                'status' => $this->callLog->status,
                'summary' => $this->callLog->summary,
                'transfer_outcome' => $this->callLog->transfer_outcome,
            ],
        ];
    }
}
