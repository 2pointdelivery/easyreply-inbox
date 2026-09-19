<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Http\Controllers\Concerns\AuthorizesTeamRole;
use Easyreply\Inbox\Models\VoiceAgent;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceSettingsController
{
    use AuthorizesTeamRole;

    public function index(Request $request, CurrentTeam $currentTeam): Response
    {
        $team = $currentTeam->resolve();

        $agents = VoiceAgent::query()
            ->where(fn ($q) => $q->where('team_id', $team?->id)->orWhereNull('team_id'))
            ->get(['id', 'team_id', 'name', 'driver', 'is_active', 'transfer_target_e164']);

        return Inertia::render('Settings/Voice', [
            'team' => $team?->only(['id', 'name', 'voice_driver', 'voice_prompt', 'voice_number_override', 'transfer_target', 'business_hours']),
            'agents' => $agents,
            'drivers' => array_keys(config('shared-inbox.voice.drivers', [])),
            'global' => [
                'driver' => config('shared-inbox.voice.driver'),
                'shared_number_e164' => config('shared-inbox.voice.shared_number_e164'),
            ],
        ]);
    }

    public function update(Request $request, CurrentTeam $currentTeam)
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);

        $validated = $request->validate([
            'voice_driver' => ['nullable', 'string'],
            'voice_prompt' => ['nullable', 'string'],
            'voice_number_override' => ['nullable', 'string'],
            'transfer_target' => ['nullable', 'string'],
            'business_hours' => ['nullable', 'array'],
        ]);

        $team->forceFill($validated)->save();

        return redirect()->back();
    }
}
