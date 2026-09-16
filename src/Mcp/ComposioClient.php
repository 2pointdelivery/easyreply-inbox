<?php

namespace Easyreply\Inbox\Mcp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper around Composio's REST API — no official Composio PHP
 * SDK is required as a dependency; this is the minimal surface
 * McpToolProvider needs. Composio is the OAuth/token custodian (see
 * BUILD_PROMPT.md §3.5): this package only ever holds a
 * composio_connection_id, never a raw provider access token.
 */
class ComposioClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        $this->apiKey ??= config('shared-inbox.mcp.composio_api_key');
        $this->baseUrl ??= config('shared-inbox.mcp.base_url', 'https://backend.composio.dev/api/v1');
    }

    public function listApps(): array
    {
        return $this->http()->get('/apps')->json('items', []);
    }

    /**
     * Start a hosted OAuth connection flow for an app. Returns Composio's
     * connection id and the URL to redirect the user's browser to.
     */
    public function initiateConnection(string $appSlug, string $redirectUrl): array
    {
        $response = $this->http()->post('/connectedAccounts', [
            'appName' => $appSlug,
            'redirectUri' => $redirectUrl,
        ])->json();

        return [
            'connection_id' => $response['connectedAccountId'] ?? $response['id'] ?? null,
            'redirect_url' => $response['redirectUrl'] ?? $response['redirect_url'] ?? null,
        ];
    }

    public function getConnection(string $connectionId): array
    {
        return $this->http()->get("/connectedAccounts/{$connectionId}")->json() ?? [];
    }

    public function deleteConnection(string $connectionId): void
    {
        $this->http()->delete("/connectedAccounts/{$connectionId}");
    }

    public function executeAction(string $actionSlug, string $connectionId, array $params): array
    {
        return $this->http()->post("/actions/{$actionSlug}/execute", [
            'connectedAccountId' => $connectionId,
            'input' => $params,
        ])->json() ?? [];
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => $this->apiKey])
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }
}
