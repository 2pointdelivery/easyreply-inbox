<?php

namespace Easyreply\Inbox\Channels;

use Easyreply\Inbox\Channels\Contracts\ChannelDriver;
use Easyreply\Inbox\Channels\Data\InboundMessageData;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Mail\ConversationReplyMail;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Parses Postmark's inbound webhook payload shape. See BUILD_PROMPT.md §11 —
 * this is the one inbound format implemented for the MVP; add a parser and
 * branch on config('shared-inbox.channels.email.inbound_format') to support
 * others (SES, Mailgun, ...).
 */
class EmailChannelDriver implements ChannelDriver
{
    public function normalizeInbound(array $payload): InboundMessageData
    {
        $fromEmail = $payload['FromFull']['Email'] ?? $payload['From'] ?? '';
        $fromName = $payload['FromFull']['Name'] ?? null;

        return new InboundMessageData(
            channelType: 'email',
            fromExternalId: Str::lower($fromEmail),
            fromDisplayName: $fromName ?: null,
            subject: $payload['Subject'] ?? null,
            body: $payload['TextBody'] ?? $payload['HtmlBody'] ?? '',
            externalId: $payload['MessageID'] ?? (string) Str::uuid(),
            inReplyToExternalId: $this->headerValue($payload, 'In-Reply-To'),
            rawPayload: $payload,
        );
    }

    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void
    {
        $fromAddress = $inbox->config['address'] ?? null;
        $toAddress = $conversation->contact->channelIdentities()
            ->where('channel_type', 'email')
            ->value('external_id');

        $mailable = (new ConversationReplyMail(
            bodyText: $message->body,
            subjectLine: $message->subject ?? $this->replySubject($conversation->subject),
        ))->from($fromAddress);

        Mail::to($toAddress)->send($mailable);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.channels.email.webhook_secret');

        if (! $secret) {
            // No secret configured: nothing to verify against. The host app
            // is expected to set SHARED_INBOX_EMAIL_WEBHOOK_SECRET before
            // exposing this endpoint publicly.
            return true;
        }

        return hash_equals($secret, (string) $request->header('X-Shared-Inbox-Webhook-Secret', ''));
    }

    public function worthProcessing(array $payload): bool
    {
        // Every call to Postmark's inbound webhook represents a real
        // inbound email — there's no other event type on this endpoint.
        return true;
    }

    protected function headerValue(array $payload, string $name): ?string
    {
        foreach ($payload['Headers'] ?? [] as $header) {
            if (Str::lower($header['Name'] ?? '') === Str::lower($name)) {
                return $header['Value'] ?? null;
            }
        }

        return null;
    }

    protected function replySubject(?string $subject): string
    {
        if (! $subject) {
            return 'Re:';
        }

        return Str::startsWith(Str::lower($subject), 're:') ? $subject : "Re: {$subject}";
    }
}
