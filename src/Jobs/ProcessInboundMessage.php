<?php

namespace Easyreply\Inbox\Jobs;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Events\MessageReceived;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Notifications\SlackRoutingNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $inboxId,
        public readonly array $payload,
    ) {}

    public function handle(ChannelManager $channels, SlackRoutingNotifier $slackRouting): void
    {
        $inbox = Inbox::findOrFail($this->inboxId);
        $driver = $channels->driver($inbox->channel_type);
        $data = $driver->normalizeInbound($this->payload);

        $contact = $this->findOrCreateContact($inbox, $data->channelType, $data->fromExternalId, $data->fromDisplayName);
        $conversation = $this->findOrCreateConversation($inbox, $contact, $data->subject, $data->inReplyToExternalId);

        if ($conversation->wasRecentlyCreated) {
            $slackRouting->notifyNewConversation($conversation);
        }

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_INBOUND,
            'channel' => $data->channelType,
            'external_id' => $data->externalId,
            'in_reply_to_external_id' => $data->inReplyToExternalId,
            'sender_type' => Contact::class,
            'sender_id' => $contact->id,
            'body' => $data->body,
            'raw_payload' => $data->rawPayload,
            'status' => Message::STATUS_SENT,
        ]);

        $message->setRelation('conversation', $conversation);
        event(new MessageReceived($message));
    }

    protected function findOrCreateContact(Inbox $inbox, string $channelType, string $externalId, ?string $displayName): Contact
    {
        $contact = Contact::query()
            ->where('team_id', $inbox->team_id)
            ->whereHas('channelIdentities', function ($query) use ($channelType, $externalId) {
                $query->where('channel_type', $channelType)->where('external_id', $externalId);
            })
            ->first();

        if ($contact) {
            return $contact;
        }

        $contact = Contact::create([
            'team_id' => $inbox->team_id,
            'display_name' => $displayName,
        ]);

        $contact->channelIdentities()->create([
            'channel_type' => $channelType,
            'external_id' => $externalId,
        ]);

        return $contact;
    }

    protected function findOrCreateConversation(Inbox $inbox, Contact $contact, ?string $subject, ?string $inReplyToExternalId): Conversation
    {
        if ($inReplyToExternalId) {
            $existing = Conversation::query()
                ->where('team_id', $inbox->team_id)
                ->whereHas('messages', fn ($query) => $query->where('external_id', $inReplyToExternalId))
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Chat-like channels (Slack, WhatsApp, Instagram) don't thread by
        // reply id the way email does — reuse the contact's existing open
        // conversation on this inbox instead of starting a new one for
        // every message. Harmless for email too: a follow-up email missing
        // In-Reply-To headers still lands in the same open conversation
        // rather than fragmenting.
        $openConversation = Conversation::query()
            ->where('team_id', $inbox->team_id)
            ->where('inbox_id', $inbox->id)
            ->where('contact_id', $contact->id)
            ->whereNotIn('status', ['closed'])
            ->first();

        if ($openConversation) {
            return $openConversation;
        }

        return Conversation::create([
            'team_id' => $inbox->team_id,
            'inbox_id' => $inbox->id,
            'contact_id' => $contact->id,
            'subject' => $subject,
            'status' => 'open',
        ]);
    }
}
