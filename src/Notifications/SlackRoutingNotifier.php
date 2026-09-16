<?php

namespace Easyreply\Inbox\Notifications;

use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Posts a notification to a Slack channel whenever a new conversation comes
 * in on ANY inbound channel — separate from Slack as an inbound channel
 * itself (see BUILD_PROMPT.md §3.3). Config-gated; a no-op unless
 * shared-inbox.slack_routing.enabled and both bot_token/channel are set.
 */
class SlackRoutingNotifier
{
    public function notifyNewConversation(Conversation $conversation): void
    {
        if (! config('shared-inbox.slack_routing.enabled')) {
            return;
        }

        $botToken = config('shared-inbox.slack_routing.bot_token');
        $channel = config('shared-inbox.slack_routing.channel');

        if (! $botToken || ! $channel) {
            return;
        }

        Http::withToken($botToken)
            ->asJson()
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => $this->messageFor($conversation),
            ]);
    }

    protected function messageFor(Conversation $conversation): string
    {
        $contactName = $conversation->contact?->display_name ?? 'Unknown contact';
        $subject = $conversation->subject ?? '(no subject)';

        return "New conversation on {$conversation->inbox->name}: *{$subject}* from {$contactName}";
    }
}
