<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Ai\AiDriverManager;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Illuminate\Http\JsonResponse;

class AiDraftController
{
    public function store(Conversation $conversation, AiDriverManager $ai): JsonResponse
    {
        $conversation->loadMissing('messages');

        $draft = $ai->driver()->draftReply($conversation);

        if (! $draft) {
            return response()->json(['draft' => null], 200);
        }

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUTBOUND,
            'channel' => $conversation->inbox->channel_type,
            'body' => $draft->body,
            'ai_generated' => true,
            'status' => Message::STATUS_DRAFT,
        ]);

        return response()->json(['draft' => $message], 201);
    }
}
