<?php

use Easyreply\Inbox\Http\Controllers\EmailWebhookController;
use Easyreply\Inbox\Http\Controllers\MetaWebhookController;
use Easyreply\Inbox\Http\Controllers\SlackWebhookController;
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
    });
