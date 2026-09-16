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
 * Parses WhatsApp Business Cloud API (Meta Graph API) inbound webhook
 * payloads. WhatsApp has no email-style threading — conversations for a
 * given contact are reused while open (see ProcessInboundMessage) rather
 * than matched by a reply-to id, though context.id is still surfaced when
 * the customer replied to a specific message.
 */
class WhatsAppChannelDriver implements ChannelDriver
{
    use VerifiesMetaSignature;

    public function normalizeInbound(array $payload): InboundMessageData
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $message = $value['messages'][0] ?? [];
        $displayName = $value['contacts'][0]['profile']['name'] ?? null;

        return new InboundMessageData(
            channelType: 'whatsapp',
            fromExternalId: $message['from'] ?? '',
            fromDisplayName: $displayName,
            subject: null,
            body: $message['text']['body'] ?? '',
            externalId: $message['id'] ?? (string) Str::uuid(),
            inReplyToExternalId: $message['context']['id'] ?? null,
            rawPayload: $payload,
        );
    }

    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void
    {
        $to = $conversation->contact->channelIdentities()
            ->where('channel_type', 'whatsapp')
            ->value('external_id');

        $phoneNumberId = $inbox->config['phone_number_id'] ?? null;

        Http::withToken($inbox->config['access_token'] ?? null)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $message->body],
            ]);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->verifyMetaSignature($request, config('shared-inbox.channels.whatsapp.app_secret'));
    }

    public function worthProcessing(array $payload): bool
    {
        // Meta also delivers status callbacks (sent/delivered/read) on this
        // same webhook — only "messages" entries are actual inbound texts.
        return ! empty($payload['entry'][0]['changes'][0]['value']['messages'] ?? null);
    }
}
