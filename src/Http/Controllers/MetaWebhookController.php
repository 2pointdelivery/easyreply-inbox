<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Jobs\ProcessInboundMessage;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles both WhatsApp Business Cloud API and Instagram Messaging webhooks
 * — the same Meta Graph API scheme (GET handshake to verify the endpoint,
 * POST to deliver events), differing only in payload shape and per-channel
 * config, both already known from $inbox->channel_type.
 */
class MetaWebhookController
{
    public function verify(Request $request, string $channel, Inbox $inbox): Response
    {
        $this->guardChannel($inbox);

        $verifyToken = config("shared-inbox.channels.{$inbox->channel_type}.verify_token");

        // Meta's handshake sends hub.mode / hub.verify_token / hub.challenge,
        // but PHP rewrites dots in query-string keys to underscores when
        // populating $_GET, so they arrive here as hub_mode etc.
        if (
            $request->query('hub_mode') === 'subscribe'
            && $verifyToken
            && hash_equals((string) $verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'));
        }

        abort(403);
    }

    public function handle(Request $request, string $channel, Inbox $inbox, ChannelManager $channels): Response
    {
        $this->guardChannel($inbox);

        $driver = $channels->driver($inbox->channel_type);

        abort_unless($driver->verifyWebhookSignature($request), 401);

        if ($driver->worthProcessing($request->all())) {
            ProcessInboundMessage::dispatch($inbox->id, $request->all());
        }

        return response()->noContent();
    }

    protected function guardChannel(Inbox $inbox): void
    {
        if (! in_array($inbox->channel_type, ['whatsapp', 'instagram'], true) || ! $inbox->is_active) {
            throw new NotFoundHttpException;
        }
    }
}
