<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController
{
    public function index(CurrentTeam $currentTeam): Response
    {
        $team = $currentTeam->resolve();

        return Inertia::render('Settings/Ai', [
            'drivers' => array_keys(config('shared-inbox.ai.drivers', [])),
            'default_driver' => config('shared-inbox.ai.driver'),
            'team_driver' => $team?->ai_driver,
        ]);
    }

    public function update(Request $request, CurrentTeam $currentTeam): RedirectResponse
    {
        $team = $currentTeam->resolve();

        abort_unless($team, 403);

        // The form submits an empty string for "use the global default" —
        // normalize before validating so it hits the `nullable` rule
        // instead of failing Rule::in (which never contains '').
        if ($request->input('ai_driver') === '') {
            $request->merge(['ai_driver' => null]);
        }

        $validated = $request->validate([
            // Null clears the override, falling back to the global
            // config('shared-inbox.ai.driver') default.
            'ai_driver' => ['nullable', 'string', Rule::in(array_keys(config('shared-inbox.ai.drivers', [])))],
        ]);

        $team->update(['ai_driver' => $validated['ai_driver'] ?? null]);

        return back();
    }
}
