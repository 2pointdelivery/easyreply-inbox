<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Mcp\McpToolProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class McpCallbackController
{
    public function __invoke(Request $request, McpToolProvider $mcp): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        try {
            $connection = $mcp->completeConnection($state);
        } catch (ValidationException $exception) {
            return redirect()->route('shared-inbox.settings.mcp')->withErrors($exception->errors());
        }

        return redirect()->route('shared-inbox.settings.mcp')->with(
            'status',
            $connection->status === 'active'
                ? "Connected {$connection->app_slug}."
                : "Could not connect {$connection->app_slug}."
        );
    }
}
