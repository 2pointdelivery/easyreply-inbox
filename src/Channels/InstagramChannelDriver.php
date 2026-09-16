<?php

namespace Easyreply\Inbox\Channels;

use Easyreply\Inbox\Channels\Concerns\VerifiesMetaSignature;
use Easyreply\Inbox\Channels\Contracts\ChannelDriver;
use Easyreply\Inbox\Channels\Data\InboundMessageData;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Parses Instagram Messaging (Meta Graph API) inbound webhook payloads —
 * the same webhook/signature scheme as WhatsApp, different payload shape.
 * Like WhatsApp, conversations are reused per contact while open rather
 * than matched by reply-to id (see ProcessInboundMessage).
 */
class InstagramChannelDriver implements ChannelDriver
{
    use VerifiesMetaSignature;

    public function normalizeInbound(array $payload): InboundMessageData
    {
        $messaging = $payload['entry'][0]['messaging'][0] ?? [];

        return new InboundMessageData(
            channelType: 'instagram',
            fromExternalId: $messaging['sender']['id'] ?? '',
            fromDisplayName: null,
            subject: null,
            body: $messaging['message']['text'] ?? '',
            externalId: $messaging['message']['mid'] ?? (string) Str::uuid(),
            inReplyToExternalId: $messaging['message']['reply_to']['mid'] ?? null,
            rawPayload: $payload,
        );
    }

    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void
    {
        $recipientId = $conversation->contact->channelIdentities()
            ->where('channel_type', 'instagram')
            ->value('external_id');

        $pageId = $inbox->config['page_id'] ?? null;

        Http::withToken($inbox->config['access_token'] ?? null)
            ->post("https://graph.facebook.com/v19.0/{$pageId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message->body],
            ]);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->verifyMetaSignature($request, config('shared-inbox.channels.instagram.app_secret'));
    }

    public function worthProcessing(array $payload): bool
    {
        // Meta also delivers read/delivery callbacks on this same webhook —
        // only entries carrying an actual "message" are inbound texts.
        return ! empty($payload['entry'][0]['messaging'][0]['message'] ?? null);
    }
}
