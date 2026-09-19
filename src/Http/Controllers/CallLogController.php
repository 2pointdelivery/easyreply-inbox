<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Models\CallAction;
use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Support\CallActionRunner;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CallLogController
{
    public function index(CurrentTeam $currentTeam): Response
    {
        $calls = CallLog::query()
            ->with(['contact', 'conversation', 'voiceAgent'])
            ->latest('created_at')
            ->paginate(20)
            ->through(fn (CallLog $call) => [
                'id' => $call->id,
                'direction' => $call->direction,
                'status' => $call->status,
                'from_e164' => $call->from_e164,
                'to_e164' => $call->to_e164,
                'provider' => $call->provider,
                'duration_seconds' => $call->duration_seconds,
                'sentiment' => $call->sentiment,
                'summary' => $call->summary,
                'conversation_id' => $call->conversation_id,
                'created_at' => $call->created_at,
                // Deliberately no audio player: strict no-audio policy. The
                // UI shows "Audio not retained by policy" instead.
                'audio_retained' => false,
            ]);

        return Inertia::render('Voice/Calls/Index', [
            'calls' => $calls,
            'team_id' => $currentTeam->resolve()?->id,
        ]);
    }

    public function show(CallLog $callLog): Response
    {
        $callLog->load(['contact', 'conversation', 'voiceAgent', 'actions']);

        return Inertia::render('Voice/Calls/Show', [
            'call' => [
                'id' => $callLog->id,
                'direction' => $callLog->direction,
                'status' => $callLog->status,
                'from_e164' => $callLog->from_e164,
                'to_e164' => $callLog->to_e164,
                'provider' => $callLog->provider,
                'provider_call_id' => $callLog->provider_call_id,
                'duration_seconds' => $callLog->duration_seconds,
                'transcript' => $callLog->transcript,
                'summary' => $callLog->summary,
                'sentiment' => $callLog->sentiment,
                'priority_suggestion' => $callLog->priority_suggestion,
                'transfer_outcome' => $callLog->transfer_outcome,
                'conversation_id' => $callLog->conversation_id,
                'contact' => $callLog->contact?->only(['id', 'display_name']),
                'audio_retained' => false,
                'actions' => $callLog->actions->map(fn (CallAction $a) => [
                    'id' => $a->id,
                    'action_type' => $a->action_type,
                    'status' => $a->status,
                    'error' => $a->error,
                    'created_at' => $a->created_at,
                ]),
            ],
        ]);
    }

    public function retry(CallLog $callLog, CallActionRunner $runner): JsonResponse
    {
        $runner->run($callLog);

        return response()->json($callLog->fresh('actions'));
    }
}
