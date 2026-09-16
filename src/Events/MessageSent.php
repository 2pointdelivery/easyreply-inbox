<?php

namespace Easyreply\Inbox\Events;

use Easyreply\Inbox\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agent's outbound reply — or a finalized AI draft — is sent,
 * so any other agent with the conversation open sees it appear live.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shared-inbox.conversation.{$this->message->conversation_id}"),
            new PrivateChannel("shared-inbox.team.{$this->message->conversation->team_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'direction' => $this->message->direction,
                'body' => $this->message->body,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at,
            ],
        ];
    }
}
