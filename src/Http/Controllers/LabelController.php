<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Http\Controllers\Concerns\AuthorizesTeamRole;
use Easyreply\Inbox\Models\Label;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelController
{
    use AuthorizesTeamRole;

    public function index(): Response
    {
        return Inertia::render('Settings/Labels', [
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request, CurrentTeam $currentTeam): RedirectResponse
    {
        $team = $currentTeam->resolve();
        $this->ensureTeamAdmin($request, $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:20'],
        ]);

        Label::create([
            'team_id' => $team->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return back();
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $this->ensureTeamAdmin($request, $label->team);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:20'],
        ]);

        $label->update($validated);

        return back();
    }

    public function destroy(Request $request, Label $label): RedirectResponse
    {
        $this->ensureTeamAdmin($request, $label->team);

        $label->delete();

        return back();
    }
}
