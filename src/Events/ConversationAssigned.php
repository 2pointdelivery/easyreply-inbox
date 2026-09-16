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
 * Fired specifically when a conversation's assignee changes, so e.g. a
 * "conversations assigned to me" view can update without reloading
 * everything ConversationUpdated would trigger a refresh for.
 */
class ConversationAssigned implements ShouldBroadcast
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
        return 'ConversationAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'assignee_id' => $this->conversation->assignee_id,
        ];
    }
}
