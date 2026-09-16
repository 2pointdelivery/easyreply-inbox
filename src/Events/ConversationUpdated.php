<?php

namespace Easyreply\Inbox\Events;

use Easyreply\Inbox\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on any conversation attribute change (status, priority, ...) other
 * than assignment, which has its own more specific ConversationAssigned
 * event.
 */
class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shared-inbox.conversation.{$this->conversation->id}"),
            new PrivateChannel("shared-inbox.team.{$this->conversation->team_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ConversationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'status' => $this->conversation->status,
                'priority' => $this->conversation->priority,
                'assignee_id' => $this->conversation->assignee_id,
            ],
        ];
    }
}
