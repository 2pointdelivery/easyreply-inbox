<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Jobs\ProcessInboundMessage;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailWebhookController
{
    public function __invoke(Request $request, Inbox $inbox, ChannelManager $channels): Response
    {
        if ($inbox->channel_type !== 'email' || ! $inbox->is_active) {
            throw new NotFoundHttpException;
        }

        $driver = $channels->driver('email');

        abort_unless($driver->verifyWebhookSignature($request), 401);

        if ($driver->worthProcessing($request->all())) {
            ProcessInboundMessage::dispatch($inbox->id, $request->all());
        }

        return response()->noContent();
    }
}
