<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationLinkController
{
    public function store(Request $request, Conversation $conversation, CurrentTeam $currentTeam, IntegrationManager $integrations): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string'],
        ]);

        $team = $currentTeam->resolve();
        $integration = $team ? $integrations->for($team, 'linear') : null;

        if (! $integration) {
            return response()->json(['link' => null, 'message' => 'Linear is not connected for this team.'], 422);
        }

        $link = $integration->linkExternalIssue($conversation, $validated);

        if (! $link) {
            return response()->json(['link' => null, 'message' => 'Could not create the Linear issue.'], 422);
        }

        return response()->json(['link' => [
            'provider' => $link->provider,
            'external_id' => $link->externalId,
            'url' => $link->url,
        ]], 201);
    }
}
