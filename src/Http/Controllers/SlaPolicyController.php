<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Http\Controllers\Concerns\AuthorizesTeamRole;
use Easyreply\Inbox\Models\SlaPolicy;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController
{
    use AuthorizesTeamRole;

    public function index(): Response
    {
        return Inertia::render('Settings/SlaPolicies', [
            'policies' => SlaPolicy::query()->orderBy('priority')->get([
                'id', 'priority', 'first_response_minutes', 'resolution_minutes',
            ]),
        ]);
    }

    public function store(Request $request, CurrentTeam $currentTeam): RedirectResponse
    {
        $team = $currentTeam->resolve();
        $this->ensureTeamAdmin($request, $team);

        $validated = $this->validated($request);

        SlaPolicy::updateOrCreate(
            ['team_id' => $team->id, 'priority' => $validated['priority']],
            [
                'first_response_minutes' => $validated['first_response_minutes'],
                'resolution_minutes' => $validated['resolution_minutes'],
            ],
        );

        return back();
    }

    public function update(Request $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        $this->ensureTeamAdmin($request, $slaPolicy->team);

        $slaPolicy->update($this->validated($request, $slaPolicy));

        return back();
    }

    public function destroy(Request $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        $this->ensureTeamAdmin($request, $slaPolicy->team);

        $slaPolicy->delete();

        return back();
    }

    protected function validated(Request $request, ?SlaPolicy $existing = null): array
    {
        return $request->validate([
            'priority' => $existing
                ? ['sometimes', 'string']
                : ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'first_response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
        ]);
    }
}
