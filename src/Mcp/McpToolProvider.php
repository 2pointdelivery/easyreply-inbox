<?php

namespace Easyreply\Inbox\Mcp;

use Easyreply\Inbox\Models\McpConnection;
use Easyreply\Inbox\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team-aware MCP tool access, backed by Composio as the OAuth/connection
 * broker (see BUILD_PROMPT.md §3.5). An AiReplyDriver implementation MAY
 * call into this to pull external context while drafting — that's an
 * optional, composable integration point, not baked into the base
 * AiReplyDriver contract.
 */
class McpToolProvider
{
    public function __construct(
        protected ComposioClient $client,
    ) {}

    public function availableApps(): array
    {
        return $this->client->listApps();
    }

    public function connectionsForTeam(Team $team): Collection
    {
        return McpConnection::query()->where('team_id', $team->id)->latest()->get();
    }

    /**
     * Begin connecting an app for a team. Returns the pending McpConnection
     * row and the URL to redirect the user's browser to for Composio's
     * hosted OAuth flow.
     *
     * @return array{connection: McpConnection, redirect_url: ?string}
     */
    public function initiateConnection(Team $team, string $appSlug, string $callbackUrl): array
    {
        $state = Str::random(40);

        $result = $this->client->initiateConnection(
            appSlug: $appSlug,
            redirectUrl: $callbackUrl.'?state='.$state,
        );

        $connection = McpConnection::create([
            'team_id' => $team->id,
            'app_slug' => $appSlug,
            'composio_connection_id' => $result['connection_id'],
            'state' => $state,
            'status' => McpConnection::STATUS_PENDING,
        ]);

        return ['connection' => $connection, 'redirect_url' => $result['redirect_url']];
    }

    /**
     * Complete a connection after the OAuth redirect back, validating the
     * round-tripped state token so a callback can't be used to activate a
     * connection it didn't initiate.
     */
    public function completeConnection(string $state): McpConnection
    {
        $connection = McpConnection::query()->where('state', $state)->first();

        if (! $connection) {
            throw ValidationException::withMessages(['state' => 'Invalid or expired connection request.']);
        }

        $status = $this->client->getConnection($connection->composio_connection_id);

        $connection->update([
            'status' => ($status['status'] ?? null) === 'ACTIVE'
                ? McpConnection::STATUS_ACTIVE
                : McpConnection::STATUS_FAILED,
            'scopes' => $status['scopes'] ?? null,
        ]);

        return $connection;
    }

    public function disconnect(McpConnection $connection): void
    {
        if ($connection->composio_connection_id) {
            $this->client->deleteConnection($connection->composio_connection_id);
        }

        $connection->update(['status' => McpConnection::STATUS_REVOKED]);
    }

    /**
     * Execute a Composio tool action on behalf of a team, within the scopes
     * their connection already authorized. Refuses to run against anything
     * but an active connection.
     */
    public function executeTool(McpConnection $connection, string $actionSlug, array $params = []): array
    {
        if ($connection->status !== McpConnection::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['connection' => 'This MCP connection is not active.']);
        }

        return $this->client->executeAction($actionSlug, $connection->composio_connection_id, $params);
    }
}
