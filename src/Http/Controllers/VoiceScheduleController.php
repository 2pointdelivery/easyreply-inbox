<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Http\Controllers\Concerns\AuthorizesTeamRole;
use Easyreply\Inbox\Models\VoiceSchedule;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceScheduleController
{
    use AuthorizesTeamRole;

    public function store(Request $request, CurrentTeam $currentTeam): JsonResponse
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);

        abort_unless(config('shared-inbox.voice.outbound.bulk'), 403, 'Scheduled/bulk calling is disabled.');

        $validated = $request->validate([
            'to_e164' => ['required', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['nullable', 'string'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $schedule = VoiceSchedule::create([
            'team_id' => $team->id,
            ...$validated,
            'status' => VoiceSchedule::STATUS_PENDING,
        ]);

        return response()->json($schedule, 201);
    }

    public function destroy(Request $request, VoiceSchedule $voiceSchedule, CurrentTeam $currentTeam): JsonResponse
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);

        abort_unless($voiceSchedule->team_id === $team->id, 403);

        $voiceSchedule->forceFill(['status' => VoiceSchedule::STATUS_CANCELLED])->save();

        return response()->json($voiceSchedule->fresh());
    }
}
