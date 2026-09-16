<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Events\MessageSent;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController
{
    public function store(Request $request, Conversation $conversation, ChannelManager $channels): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUTBOUND,
            'channel' => $conversation->inbox->channel_type,
            'sender_type' => $user ? $user::class : null,
            'sender_id' => $user?->getKey(),
            'body' => $validated['body'],
            'status' => Message::STATUS_SENT,
        ]);

        $channels->driver($conversation->inbox->channel_type)->send(
            $conversation->inbox,
            $conversation,
            new OutboundMessageData($validated['body']),
        );

        $message->setRelation('conversation', $conversation);
        event(new MessageSent($message));

        return response()->json($message, 201);
    }

    /**
     * Finalize and send an existing draft (typically an AI-generated one
     * from AiDraftController) — the agent may edit the body first. Updates
     * the same Message row rather than creating a new one, so it stays
     * "the draft" that became "the sent message".
     */
    public function send(Request $request, Message $message, ChannelManager $channels): JsonResponse
    {
        abort_if($message->status === Message::STATUS_SENT, 422, 'Message has already been sent.');

        $validated = $request->validate([
            'body' => ['sometimes', 'string'],
        ]);

        $conversation = $message->conversation()->with(['inbox', 'contact'])->firstOrFail();
        $user = $request->user();

        $message->fill([
            'body' => $validated['body'] ?? $message->body,
            'status' => Message::STATUS_SENT,
            'sender_type' => $user ? $user::class : $message->sender_type,
            'sender_id' => $user?->getKey() ?? $message->sender_id,
        ])->save();

        $channels->driver($conversation->inbox->channel_type)->send(
            $conversation->inbox,
            $conversation,
            new OutboundMessageData($message->body),
        );

        $message->setRelation('conversation', $conversation);
        event(new MessageSent($message));

        return response()->json($message);
    }
}
