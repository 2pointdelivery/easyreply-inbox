<?php

namespace Easyreply\Inbox\Channels;

use Easyreply\Inbox\Channels\Contracts\ChannelDriver;
use Easyreply\Inbox\Channels\Data\InboundMessageData;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Website widget channel (floating chat bubble). Inbound guest messages
 * arrive via the widget's own public endpoints (see WidgetChatController),
 * already normalized — this driver only satisfies the ChannelDriver
 * contract so agent replies route through the standard MessageController
 * path. Outbound delivery is a no-op: the reply is stored as a Message and
 * the guest picks it up via widget polling (plus broadcast events).
 */
class WidgetChannelDriver implements ChannelDriver
{
    public function normalizeInbound(array $payload): InboundMessageData
    {
        return new InboundMessageData(
            channelType: 'widget',
            fromExternalId: (string) ($payload['visitor_token'] ?? ''),
            fromDisplayName: $payload['name'] ?? null,
            subject: null,
            body: (string) ($payload['body'] ?? ''),
            externalId: (string) ($payload['message_id'] ?? Str::uuid()),
            inReplyToExternalId: null,
            rawPayload: [],
        );
    }

    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void
    {
        // Intentional no-op: the reply is already persisted as a Message by
        // the caller, and the guest widget polls the public messages
        // endpoint (MessageReceived is still broadcast for live agent UI).
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function worthProcessing(array $payload): bool
    {
        return trim((string) ($payload['body'] ?? '')) !== '';
    }
}
