<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Events\ConversationAssigned;
use Easyreply\Inbox\Events\ConversationUpdated;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController
{
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'pending', 'closed', 'snoozed'])],
            'priority' => ['sometimes', 'string'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $assigneeChanged = array_key_exists('assignee_id', $validated)
            && $validated['assignee_id'] !== $conversation->assignee_id;

        $conversation->fill($validated);
        $wasChanged = $conversation->isDirty();
        $conversation->save();

        if ($assigneeChanged) {
            event(new ConversationAssigned($conversation));
        }

        if ($wasChanged) {
            event(new ConversationUpdated($conversation));
        }

        return response()->json($conversation);
    }
}
