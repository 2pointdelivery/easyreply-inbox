<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\IntegrationSetting;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationSettingsController
{
    public function index(CurrentTeam $currentTeam, IntegrationManager $integrations): Response
    {
        $team = $currentTeam->resolve();

        $settings = collect($integrations->availableKeys())
            ->mapWithKeys(function (string $key) use ($team) {
                $config = config("shared-inbox.integrations.{$key}");
                $setting = $team
                    ? IntegrationSetting::query()
                        ->where('team_id', $team->id)
                        ->where('integration_key', $key)
                        ->first()
                    : null;

                return [$key => [
                    'key' => $key,
                    'available' => (bool) ($config['enabled'] ?? false),
                    'enabled' => $setting?->enabled ?? false,
                    'configured' => ! empty($setting?->config),
                ]];
            });

        return Inertia::render('Settings/Integrations', [
            'integrations' => $settings->values(),
        ]);
    }

    public function update(Request $request, string $key, CurrentTeam $currentTeam): RedirectResponse
    {
        $team = $currentTeam->resolve();

        abort_unless($team, 403);
        abort_unless(array_key_exists($key, config('shared-inbox.integrations', [])), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'config' => ['sometimes', 'array'],
        ]);

        IntegrationSetting::updateOrCreate(
            ['team_id' => $team->id, 'integration_key' => $key],
            [
                'enabled' => $validated['enabled'],
                'config' => $validated['config'] ?? [],
            ],
        );

        return back();
    }
}
