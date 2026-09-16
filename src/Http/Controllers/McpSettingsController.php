<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Mcp\McpToolProvider;
use Easyreply\Inbox\Models\McpConnection;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class McpSettingsController
{
    public function index(CurrentTeam $currentTeam, McpToolProvider $mcp): Response
    {
        $team = $currentTeam->resolve();

        return Inertia::render('Settings/Mcp', [
            'apps' => $mcp->availableApps(),
            'connections' => $team ? $mcp->connectionsForTeam($team) : [],
        ]);
    }

    public function connect(Request $request, string $appSlug, CurrentTeam $currentTeam, McpToolProvider $mcp): RedirectResponse
    {
        $team = $currentTeam->resolve();

        abort_unless($team, 403);

        $result = $mcp->initiateConnection($team, $appSlug, route('shared-inbox.mcp.callback'));

        if (! $result['redirect_url']) {
            return back()->withErrors(['mcp' => 'Could not start the connection with Composio.']);
        }

        return redirect()->away($result['redirect_url']);
    }

    public function disconnect(McpConnection $connection, McpToolProvider $mcp): RedirectResponse
    {
        $mcp->disconnect($connection);

        return back();
    }
}
