<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Jobs\ProcessInboundMessage;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SlackWebhookController
{
    public function __invoke(Request $request, Inbox $inbox, ChannelManager $channels): Response
    {
        // Slack's one-time URL verification handshake, sent when the Events
        // API subscription is first configured — must echo the challenge
        // back verbatim, before any signature/inbox checks.
        if ($request->input('type') === 'url_verification') {
            return response($request->input('challenge'));
        }

        if ($inbox->channel_type !== 'slack' || ! $inbox->is_active) {
            throw new NotFoundHttpException;
        }

        $driver = $channels->driver('slack');

        abort_unless($driver->verifyWebhookSignature($request), 401);

        if ($driver->worthProcessing($request->all())) {
            ProcessInboundMessage::dispatch($inbox->id, $request->all());
        }

        return response()->noContent();
    }
}
