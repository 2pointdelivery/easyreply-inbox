<?php

namespace Easyreply\Inbox\Integrations;

use Easyreply\Inbox\Integrations\Contracts\Integration;
use Easyreply\Inbox\Integrations\Data\ExternalLink;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Pulls CRM context (company, deal, past tickets) for a contact
 * (BUILD_PROMPT.md §3.7). HubSpot isn't an issue tracker or status page, so
 * linkExternalIssue/fetchIncidentStatus are no-ops.
 *
 * $settings (from IntegrationSetting.config, per team):
 *   - api_key: HubSpot private app access token
 */
class HubSpotIntegration implements Integration
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
        $apiKey = $this->settings['api_key'] ?? null;
        $email = $contact->channelIdentities()->where('channel_type', 'email')->value('external_id');

        if (! $apiKey || ! $email) {
            return [];
        }

        $response = Http::withToken($apiKey)
            ->get('https://api.hubapi.com/crm/v3/objects/contacts/'.urlencode($email), [
                'idProperty' => 'email',
                'properties' => 'company,lifecyclestage',
            ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json('properties', []);
    }

    public function fetchIncidentStatus(): array
    {
        return [];
    }
}
