<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\VoiceAgent;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Easyreply\Inbox\Voice\Data\OutboundCallData;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClickToCallController
{
    public function store(Request $request, Conversation $conversation, VoiceAgentManager $voices, CurrentTeam $currentTeam): JsonResponse
    {
        $validated = $request->validate([
            'to_e164' => ['required', 'string'],
            'context' => ['nullable', 'string'],
        ]);

        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');

        $driverName = $team->voice_driver;
        $agent = VoiceAgent::query()
            ->where(fn ($q) => $q->where('team_id', $team->id)->orWhereNull('team_id'))
            ->where('is_active', true)
            ->first();

        $call = CallLog::create([
            'team_id' => $team->id,
            'voice_agent_id' => $agent?->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'inbox_id' => $conversation->inbox_id,
            'direction' => CallLog::DIRECTION_OUTBOUND,
            'from_e164' => $team->voice_number_override ?? config('shared-inbox.voice.shared_number_e164'),
            'to_e164' => $validated['to_e164'],
            'provider' => $driverName ?? config('shared-inbox.voice.driver', 'null'),
            'provider_call_id' => 'pending-'.uniqid(),
            'status' => CallLog::STATUS_QUEUED,
            'started_at' => now(),
        ]);

        $providerCallId = $voices->driver($driverName)->initiateOutbound(
            $call,
            new OutboundCallData($validated['to_e164'], $validated['context'] ?? null, $conversation->id)
        );

        $call->forceFill([
            'provider_call_id' => $providerCallId,
            'status' => CallLog::STATUS_RINGING,
        ])->save();

        return response()->json($call->fresh(), 201);
    }
}
