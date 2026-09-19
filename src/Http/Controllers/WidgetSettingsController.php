<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Http\Controllers\Concerns\AuthorizesTeamRole;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WidgetSettingsController
{
    use AuthorizesTeamRole;

    public function index(Request $request, CurrentTeam $currentTeam): Response
    {
        $team = $currentTeam->resolve();

        $inboxes = Inbox::query()
            ->where('channel_type', 'widget')
            ->get(['id', 'name', 'config', 'is_active'])
            ->map(fn (Inbox $inbox) => [
                'id' => $inbox->id,
                'name' => $inbox->name,
                'is_active' => $inbox->is_active,
                'widget_enabled' => (bool) ($inbox->config['widget_enabled'] ?? false),
                'token_hint' => $this->hint($inbox->config['widget_token'] ?? null),
                'snippet' => $this->snippet($request, $inbox),
            ]);

        return Inertia::render('Settings/Widget', ['inboxes' => $inboxes]);
    }

    public function store(Request $request, CurrentTeam $currentTeam)
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        Inbox::create([
            'team_id' => $team->id,
            'channel_type' => 'widget',
            'name' => $validated['name'],
            'config' => ['widget_enabled' => true, 'widget_token' => Str::random(40)],
            'is_active' => true,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Inbox $inbox, CurrentTeam $currentTeam)
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);
        abort_unless($inbox->team_id === $team->id && $inbox->channel_type === 'widget', 404);

        $validated = $request->validate(['widget_enabled' => ['required', 'boolean']]);

        $config = $inbox->config ?? [];
        $config['widget_enabled'] = $validated['widget_enabled'];
        $config['widget_token'] ??= Str::random(40);
        $inbox->forceFill(['config' => $config])->save();

        return redirect()->back();
    }

    public function regenerate(Request $request, Inbox $inbox, CurrentTeam $currentTeam)
    {
        $team = $currentTeam->resolve();
        abort_unless($team, 403, 'You do not belong to a team.');
        $this->ensureTeamAdmin($request, $team);
        abort_unless($inbox->team_id === $team->id && $inbox->channel_type === 'widget', 404);

        $config = $inbox->config ?? [];
        $config['widget_token'] = Str::random(40);
        $inbox->forceFill(['config' => $config])->save();

        return redirect()->back();
    }

    protected function hint(?string $token): ?string
    {
        return $token ? '…'.substr($token, -4) : null;
    }

    protected function snippet(Request $request, Inbox $inbox): ?string
    {
        $token = $inbox->config['widget_token'] ?? null;

        if (! $token) {
            return null;
        }

        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        return '<script src="'.$base.'/shared-inbox/widget.js" data-inbox="'.$inbox->id.'" data-token="'.$token.'" defer></script>';
    }
}
