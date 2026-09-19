<?php

use Easyreply\Inbox\Http\Controllers\EmailWebhookController;
use Easyreply\Inbox\Http\Controllers\MetaWebhookController;
use Easyreply\Inbox\Http\Controllers\SlackWebhookController;
use Easyreply\Inbox\Http\Controllers\VoiceWebhookController;
use Easyreply\Inbox\Http\Controllers\WidgetCallController;
use Easyreply\Inbox\Http\Controllers\WidgetChatController;
use Easyreply\Inbox\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

// This file is deliberately just external, unauthenticated (signature-
// verified) provider webhooks — stateless, no session/CSRF. First-party
// JSON endpoints the Inertia UI itself calls (sending a message, ai-draft,
// notes, labels, mcp) live in routes/web.php instead, under the 'web'
// middleware group, so they share the browser session/CSRF like the rest of
// the Inertia app.

Route::prefix('shared-inbox')
    ->middleware(['api'])
    ->group(function () {
        Route::post('webhooks/email/{inbox}', EmailWebhookController::class)
            ->middleware('throttle:60,1')
            ->name('shared-inbox.webhooks.email');

        Route::post('webhooks/slack/{inbox}', SlackWebhookController::class)
            ->middleware('throttle:60,1')
            ->name('shared-inbox.webhooks.slack');

        // WhatsApp and Instagram both use Meta's GET-to-verify /
        // POST-to-deliver webhook scheme (see MetaWebhookController).
        Route::get('webhooks/{channel}/{inbox}', [MetaWebhookController::class, 'verify'])
            ->whereIn('channel', ['whatsapp', 'instagram'])
            ->name('shared-inbox.webhooks.meta.verify');

        Route::post('webhooks/{channel}/{inbox}', [MetaWebhookController::class, 'handle'])
            ->whereIn('channel', ['whatsapp', 'instagram'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.webhooks.meta.handle');

        // Voice agent provider webhooks (stateless, signature-verified).
        // Never handles audio — transcript + metadata only.
        Route::post('webhooks/voice/{provider}', [VoiceWebhookController::class, 'handle'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.webhooks.voice');
        Route::post('voice/{provider}/status-callback', [VoiceWebhookController::class, 'status'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.voice.status');
        Route::post('voice/{provider}/transfer-callback', [VoiceWebhookController::class, 'transfer'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.voice.transfer');

        // Public website widget (floating chat + call requests). Stateless,
        // inbox-token authed, no session — guests are not logged-in users.
        // Tighter throttles on session-creating and dial-triggering routes
        // to blunt spam/abuse.
        Route::get('widget.js', [WidgetController::class, 'loader'])
            ->name('shared-inbox.widget.loader');
        Route::get('widget', [WidgetController::class, 'page'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.widget.page');
        Route::post('widget/{inbox}/start', [WidgetChatController::class, 'start'])
            ->middleware('throttle:10,1')
            ->name('shared-inbox.widget.start');
        Route::get('widget/{inbox}/messages', [WidgetChatController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.widget.messages.index');
        Route::post('widget/{inbox}/messages', [WidgetChatController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.widget.messages.store');
        Route::get('widget/{inbox}/status', [WidgetChatController::class, 'status'])
            ->middleware('throttle:60,1')
            ->name('shared-inbox.widget.status');
        Route::post('widget/{inbox}/call-request', [WidgetCallController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('shared-inbox.widget.call-request');
    });
