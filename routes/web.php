<?php

use Easyreply\Inbox\Http\Controllers\AiDraftController;
use Easyreply\Inbox\Http\Controllers\ConversationController;
use Easyreply\Inbox\Http\Controllers\ConversationLinkController;
use Easyreply\Inbox\Http\Controllers\InboxController;
use Easyreply\Inbox\Http\Controllers\IntegrationSettingsController;
use Easyreply\Inbox\Http\Controllers\McpCallbackController;
use Easyreply\Inbox\Http\Controllers\McpSettingsController;
use Easyreply\Inbox\Http\Controllers\MessageController;
use Easyreply\Inbox\Http\Middleware\EnsureTeamContext;
use Illuminate\Support\Facades\Route;

// Other settings screens (channel connections, labels/SLA, team) are added
// in later build phases — see BUILD_PROMPT.md §6.

Route::prefix('shared-inbox')
    ->middleware(['web', EnsureTeamContext::class])
    ->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('shared-inbox.index');
        Route::get('/conversations/{conversation}', [InboxController::class, 'show'])
            ->name('shared-inbox.conversations.show');
        Route::patch('/conversations/{conversation}', [ConversationController::class, 'update'])
            ->name('shared-inbox.conversations.update');

        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->name('shared-inbox.conversations.messages.store');
        Route::patch('/messages/{message}/send', [MessageController::class, 'send'])
            ->name('shared-inbox.messages.send');

        Route::post('/conversations/{conversation}/ai-draft', [AiDraftController::class, 'store'])
            ->name('shared-inbox.conversations.ai-draft');

        Route::post('/conversations/{conversation}/link', [ConversationLinkController::class, 'store'])
            ->name('shared-inbox.conversations.link');

        Route::get('/settings/integrations', [IntegrationSettingsController::class, 'index'])
            ->name('shared-inbox.settings.integrations');
        Route::patch('/settings/integrations/{key}', [IntegrationSettingsController::class, 'update'])
            ->name('shared-inbox.settings.integrations.update');

        Route::get('/settings/mcp', [McpSettingsController::class, 'index'])
            ->name('shared-inbox.settings.mcp');
        Route::post('/settings/mcp/{appSlug}/connect', [McpSettingsController::class, 'connect'])
            ->name('shared-inbox.settings.mcp.connect');
        Route::delete('/settings/mcp/{connection}', [McpSettingsController::class, 'disconnect'])
            ->name('shared-inbox.settings.mcp.disconnect');

        // Composio redirects the user's browser back here after the hosted
        // OAuth flow completes.
        Route::get('/mcp/callback', McpCallbackController::class)
            ->name('shared-inbox.mcp.callback');
    });
