<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Ai\AiDriverManager;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\JsonResponse;

class AiDraftController
{
    public function store(Conversation $conversation, AiDriverManager $ai, CurrentTeam $currentTeam): JsonResponse
    {
        $conversation->loadMissing('messages');

        // A team's own ai_driver overrides config('shared-inbox.ai.driver')
        // when set (see AiSettingsController) — global config stays the
        // default for every team that hasn't chosen one.
        $driverName = $currentTeam->resolve()?->ai_driver;

        $draft = $ai->driver($driverName)->draftReply($conversation);

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
