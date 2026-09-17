<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Events\ConversationAssigned;
use Easyreply\Inbox\Events\ConversationUpdated;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Label;
use Easyreply\Inbox\Support\SlaCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class ConversationController
{
    public function update(Request $request, Conversation $conversation, SlaCalculator $sla): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'pending', 'closed', 'snoozed'])],
            'priority' => ['sometimes', 'string'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $assigneeChanged = array_key_exists('assignee_id', $validated)
            && $validated['assignee_id'] !== $conversation->assignee_id;

        $priorityChanged = array_key_exists('priority', $validated)
            && $validated['priority'] !== $conversation->priority;

        $closing = ($validated['status'] ?? null) === 'closed' && $conversation->status !== 'closed';

        $conversation->fill($validated);

        if ($priorityChanged) {
            $conversation->sla_due_at = $sla->dueAt($conversation->team_id, $validated['priority']);
        }

        if ($closing && ! $conversation->resolved_at) {
            $conversation->resolved_at = Date::now();
        }

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

    public function attachLabel(Conversation $conversation, Label $label): JsonResponse
    {
        $conversation->labels()->syncWithoutDetaching([$label->id]);

        return response()->json($conversation->load('labels'));
    }

    public function detachLabel(Conversation $conversation, Label $label): JsonResponse
    {
        $conversation->labels()->detach($label->id);

        return response()->json($conversation->load('labels'));
    }
}
