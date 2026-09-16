<?php

namespace Easyreply\Inbox\Integrations;

use Easyreply\Inbox\Integrations\Contracts\Integration;
use Easyreply\Inbox\Models\IntegrationSetting;
use Easyreply\Inbox\Models\Team;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a team's Integration instance for a key (linear, hubspot,
 * betterstack), respecting two independent flags: the package-wide
 * config('shared-inbox.integrations.{key}.enabled') switch, and the team's
 * own IntegrationSetting row — both must be on. Returns null otherwise, so
 * callers never need to know why an integration isn't available, just that
 * it isn't (see BUILD_PROMPT.md §3.7: never required for core functionality).
 */
class IntegrationManager
{
    public function __construct(
        protected Container $container,
    ) {}

    public function for(Team $team, string $key): ?Integration
    {
        $config = $this->container->make('config')->get("shared-inbox.integrations.{$key}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return null;
        }

        $setting = IntegrationSetting::query()
            ->where('team_id', $team->id)
            ->where('integration_key', $key)
            ->first();

        if (! $setting || ! $setting->enabled) {
            return null;
        }

        return $this->container->make($config['driver'], ['settings' => $setting->config ?? []]);
    }

    /**
     * @return array<int, string>
     */
    public function availableKeys(): array
    {
        return array_keys($this->container->make('config')->get('shared-inbox.integrations', []));
    }
}
