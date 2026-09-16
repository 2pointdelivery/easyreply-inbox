<?php

namespace Easyreply\Inbox\Integrations;

use Easyreply\Inbox\Integrations\Contracts\Integration;
use Easyreply\Inbox\Integrations\Data\ExternalLink;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Links recurring issues to Linear (BUILD_PROMPT.md §3.7). Linear isn't a
 * CRM or status page, so fetchCustomerContext/fetchIncidentStatus are no-ops.
 *
 * $settings (from IntegrationSetting.config, per team):
 *   - api_key: Linear personal API key or OAuth token
 *   - team_id: the Linear team new issues are created under
 */
class LinearIntegration implements Integration
{
    public function __construct(
        protected array $settings,
    ) {}

    public function linkExternalIssue(Conversation $conversation, array $attributes): ?ExternalLink
    {
        $apiKey = $this->settings['api_key'] ?? null;
        $linearTeamId = $this->settings['team_id'] ?? null;

        if (! $apiKey || ! $linearTeamId) {
            return null;
        }

        $response = Http::withHeaders(['Authorization' => $apiKey])
            ->post('https://api.linear.app/graphql', [
                'query' => 'mutation IssueCreate($input: IssueCreateInput!) {
                    issueCreate(input: $input) {
                        success
                        issue { id url }
                    }
                }',
                'variables' => [
                    'input' => [
                        'teamId' => $linearTeamId,
                        'title' => $attributes['title'] ?? ($conversation->subject ?? 'Support conversation'),
                        'description' => $attributes['description'] ?? null,
                    ],
                ],
            ]);

        $issue = $response->json('data.issueCreate.issue');

        if (! $issue) {
            return null;
        }

        return new ExternalLink(provider: 'linear', externalId: $issue['id'], url: $issue['url']);
    }

    public function fetchCustomerContext(Contact $contact): array
    {
        return [];
    }

    public function fetchIncidentStatus(): array
    {
        return [];
    }
}
