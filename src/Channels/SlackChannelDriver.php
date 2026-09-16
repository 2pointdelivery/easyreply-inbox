<?php

namespace Easyreply\Inbox\Channels;

use Easyreply\Inbox\Channels\Contracts\ChannelDriver;
use Easyreply\Inbox\Channels\Data\InboundMessageData;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Parses Slack's Events API "message" event callback payload. Threads
 * replies via Slack's own thread_ts. Sending uses the Slack Web API
 * (chat.postMessage) with the inbox's connected bot token.
 */
class SlackChannelDriver implements ChannelDriver
{
    public function normalizeInbound(array $payload): InboundMessageData
    {
        $event = $payload['event'] ?? [];

        return new InboundMessageData(
            channelType: 'slack',
            fromExternalId: $event['user'] ?? '',
            // Slack doesn't include a display name on the message event
            // itself (requires a separate users.info API call) — left null
            // for the MVP driver; Contact::display_name stays empty until
            // that lookup is added.
            fromDisplayName: null,
            subject: null,
            body: $event['text'] ?? '',
            externalId: $event['ts'] ?? (string) Str::uuid(),
            inReplyToExternalId: $event['thread_ts'] ?? null,
            rawPayload: $payload,
        );
    }

    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void
    {
        $channel = $conversation->contact->channelIdentities()
            ->where('channel_type', 'slack')
            ->value('external_id');

        Http::withToken($inbox->config['bot_token'] ?? null)
            ->asJson()
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => $message->body,
            ]);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('shared-inbox.channels.slack.signing_secret');

        if (! $secret) {
            return true;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! $timestamp || ! $signature) {
            return false;
        }

        // Reject requests older than 5 minutes to guard against replay.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $computed = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret);

        return hash_equals($computed, $signature);
    }

    public function worthProcessing(array $payload): bool
    {
        if (($payload['type'] ?? null) === 'url_verification') {
            return false;
        }

        $event = $payload['event'] ?? [];

        if (($event['type'] ?? null) !== 'message') {
            return false;
        }

        // Ignore the bot's own messages (Slack echoes them back on the same
        // event stream) to avoid processing loops.
        if (! empty($event['bot_id']) || ($event['subtype'] ?? null) === 'bot_message') {
            return false;
        }

        return true;
    }
}
