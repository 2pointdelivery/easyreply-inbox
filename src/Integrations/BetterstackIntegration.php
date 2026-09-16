<?php

namespace Easyreply\Inbox\Integrations;

use Easyreply\Inbox\Integrations\Contracts\Integration;
use Easyreply\Inbox\Integrations\Data\ExternalLink;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Surfaces current unresolved incidents from Betterstack's status page
 * (BUILD_PROMPT.md §3.7). Betterstack isn't an issue tracker or CRM, so
 * linkExternalIssue/fetchCustomerContext are no-ops.
 *
 * $settings (from IntegrationSetting.config, per team):
 *   - api_key: Betterstack API token
 */
class BetterstackIntegration implements Integration
{
    public function __construct(
        protected array $settings,
    ) {}

    public function linkExternalIssue(Conversation $conversation, array $attributes): ?ExternalLink
    {
        return null;
    }

    public function fetchCustomerContext(Contact $contact): array
    {
        return [];
    }

    public function fetchIncidentStatus(): array
    {
        $apiKey = $this->settings['api_key'] ?? null;

        if (! $apiKey) {
            return [];
        }

        $response = Http::withToken($apiKey)->get('https://uptime.betterstack.com/api/v2/incidents');

        if ($response->failed()) {
            return [];
        }

        return collect($response->json('data', []))
            ->filter(fn (array $incident) => is_null($incident['attributes']['resolved_at'] ?? null))
            ->values()
            ->all();
    }
}
