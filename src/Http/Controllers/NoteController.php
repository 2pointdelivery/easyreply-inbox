<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'mentioned_user_ids' => ['sometimes', 'array'],
            'mentioned_user_ids.*' => ['integer'],
        ]);

        // Mentions are restricted to users who actually belong to this
        // conversation's team, so a note can't be used to probe/notify
        // arbitrary user ids from another team.
        $teamUserIds = $conversation->team->users()->pluck('users.id');
        $mentionedUserIds = collect($validated['mentioned_user_ids'] ?? [])
            ->intersect($teamUserIds)
            ->values()
            ->all();

        $note = $conversation->notes()->create([
            'user_id' => $request->user()->getKey(),
            'body' => $validated['body'],
            'mentioned_user_ids' => $mentionedUserIds,
        ]);

        return response()->json($note, 201);
    }
}
