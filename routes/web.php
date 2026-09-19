<?php

use Easyreply\Inbox\Http\Controllers\AiDraftController;
use Easyreply\Inbox\Http\Controllers\AiSettingsController;
use Easyreply\Inbox\Http\Controllers\CallLogController;
use Easyreply\Inbox\Http\Controllers\ClickToCallController;
use Easyreply\Inbox\Http\Controllers\ConversationController;
use Easyreply\Inbox\Http\Controllers\ConversationLinkController;
use Easyreply\Inbox\Http\Controllers\InboxController;
use Easyreply\Inbox\Http\Controllers\IntegrationSettingsController;
use Easyreply\Inbox\Http\Controllers\LabelController;
use Easyreply\Inbox\Http\Controllers\McpCallbackController;
use Easyreply\Inbox\Http\Controllers\McpSettingsController;
use Easyreply\Inbox\Http\Controllers\MessageController;
use Easyreply\Inbox\Http\Controllers\NoteController;
use Easyreply\Inbox\Http\Controllers\SlaPolicyController;
use Easyreply\Inbox\Http\Controllers\VoiceScheduleController;
use Easyreply\Inbox\Http\Controllers\VoiceSettingsController;
use Easyreply\Inbox\Http\Middleware\EnsureTeamContext;
use Illuminate\Support\Facades\Route;

// Other settings screens (channel connections, team) are added in later
// build phases — see BUILD_PROMPT.md §6.

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

        Route::post('/conversations/{conversation}/notes', [NoteController::class, 'store'])
            ->name('shared-inbox.conversations.notes.store');

        Route::post('/conversations/{conversation}/labels/{label}', [ConversationController::class, 'attachLabel'])
            ->name('shared-inbox.conversations.labels.attach');
        Route::delete('/conversations/{conversation}/labels/{label}', [ConversationController::class, 'detachLabel'])
            ->name('shared-inbox.conversations.labels.detach');

        Route::get('/settings/integrations', [IntegrationSettingsController::class, 'index'])
            ->name('shared-inbox.settings.integrations');
        Route::patch('/settings/integrations/{key}', [IntegrationSettingsController::class, 'update'])
            ->name('shared-inbox.settings.integrations.update');

        Route::get('/settings/labels', [LabelController::class, 'index'])
            ->name('shared-inbox.settings.labels');
        Route::post('/settings/labels', [LabelController::class, 'store'])
            ->name('shared-inbox.settings.labels.store');
        Route::patch('/settings/labels/{label}', [LabelController::class, 'update'])
            ->name('shared-inbox.settings.labels.update');
        Route::delete('/settings/labels/{label}', [LabelController::class, 'destroy'])
            ->name('shared-inbox.settings.labels.destroy');

        Route::get('/settings/sla-policies', [SlaPolicyController::class, 'index'])
            ->name('shared-inbox.settings.sla-policies');
        Route::post('/settings/sla-policies', [SlaPolicyController::class, 'store'])
            ->name('shared-inbox.settings.sla-policies.store');
        Route::patch('/settings/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update'])
            ->name('shared-inbox.settings.sla-policies.update');
        Route::delete('/settings/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'destroy'])
            ->name('shared-inbox.settings.sla-policies.destroy');

        Route::get('/settings/ai', [AiSettingsController::class, 'index'])
            ->name('shared-inbox.settings.ai');
        Route::patch('/settings/ai', [AiSettingsController::class, 'update'])
            ->name('shared-inbox.settings.ai.update');

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

        Route::get('/voice/calls', [CallLogController::class, 'index'])
            ->name('shared-inbox.voice.calls.index');
        Route::get('/voice/calls/{callLog}', [CallLogController::class, 'show'])
            ->name('shared-inbox.voice.calls.show');
        Route::post('/voice/calls/{callLog}/actions/retry', [CallLogController::class, 'retry'])
            ->name('shared-inbox.voice.calls.retry');

        Route::post('/conversations/{conversation}/click-to-call', [ClickToCallController::class, 'store'])
            ->name('shared-inbox.conversations.click-to-call');

        Route::get('/settings/voice', [VoiceSettingsController::class, 'index'])
            ->name('shared-inbox.settings.voice');
        Route::patch('/settings/voice', [VoiceSettingsController::class, 'update'])
            ->name('shared-inbox.settings.voice.update');

        Route::post('/voice/schedules', [VoiceScheduleController::class, 'store'])
            ->name('shared-inbox.voice.schedules.store');
        Route::delete('/voice/schedules/{voiceSchedule}', [VoiceScheduleController::class, 'destroy'])
            ->name('shared-inbox.voice.schedules.destroy');
    });
