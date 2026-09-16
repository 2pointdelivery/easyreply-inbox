<?php

namespace Easyreply\Inbox\Channels\Contracts;

use Easyreply\Inbox\Channels\Data\InboundMessageData;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Illuminate\Http\Request;

interface ChannelDriver
{
    /**
     * Turn a raw provider webhook payload into the package's channel-agnostic
     * shape. Implementations do not touch the database.
     */
    public function normalizeInbound(array $payload): InboundMessageData;

    /**
     * Deliver an outbound reply through this channel for the given
     * conversation (e.g. send an email, post to Slack).
     */
    public function send(Inbox $inbox, Conversation $conversation, OutboundMessageData $message): void;

    /**
     * Verify that an inbound webhook request actually came from the
     * provider before any payload is processed or persisted.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Whether this webhook payload represents an actual inbound message
     * worth turning into a Conversation/Message — providers also deliver
     * non-message events on the same webhook (Slack's other event types,
     * WhatsApp/Instagram delivery & read receipts, a bot's own echoed
     * message). Controllers check this before dispatching the processing
     * job so webhook noise never reaches the database.
     */
    public function worthProcessing(array $payload): bool;
}
