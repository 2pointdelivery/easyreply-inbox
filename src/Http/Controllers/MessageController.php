<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Channels\Data\OutboundMessageData;
use Easyreply\Inbox\Events\MessageSent;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class MessageController
{
    public function store(Request $request, Conversation $conversation, ChannelManager $channels): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.config('shared-inbox.attachments.max_size_kb', 10240)],
        ]);

        $user = $request->user();

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUTBOUND,
            'channel' => $conversation->inbox->channel_type,
            'sender_type' => $user ? $user::class : null,
            'sender_id' => $user?->getKey(),
            'body' => $validated['body'],
            'status' => Message::STATUS_SENT,
        ]);

        $this->storeAttachments($message, $request);

        if (! $conversation->first_response_at) {
            $conversation->forceFill(['first_response_at' => Date::now()])->save();
        }

        $channels->driver($conversation->inbox->channel_type)->send(
            $conversation->inbox,
            $conversation,
            new OutboundMessageData($validated['body']),
        );

        $message->setRelation('conversation', $conversation);
        $message->load('attachments');
        event(new MessageSent($message));

        return response()->json($message, 201);
    }

    /**
     * Local storage + UI display only — attachments aren't forwarded through
     * ChannelDriver::send() to the provider yet (documented follow-up, see
     * README "Known limitations"). Keeps this addition self-contained rather
     * than reworking every channel driver's send signature for it.
     */
    protected function storeAttachments(Message $message, Request $request): void
    {
        $disk = config('shared-inbox.attachments.disk', 'local');

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('shared-inbox/attachments', $disk);

            $message->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Finalize and send an existing draft (typically an AI-generated one
     * from AiDraftController) — the agent may edit the body first. Updates
     * the same Message row rather than creating a new one, so it stays
     * "the draft" that became "the sent message".
     */
    public function send(Request $request, Message $message, ChannelManager $channels): JsonResponse
    {
        abort_if($message->status === Message::STATUS_SENT, 422, 'Message has already been sent.');

        $validated = $request->validate([
            'body' => ['sometimes', 'string'],
        ]);

        $conversation = $message->conversation()->with(['inbox', 'contact'])->firstOrFail();
        $user = $request->user();

        $message->fill([
            'body' => $validated['body'] ?? $message->body,
            'status' => Message::STATUS_SENT,
            'sender_type' => $user ? $user::class : $message->sender_type,
            'sender_id' => $user?->getKey() ?? $message->sender_id,
        ])->save();

        if (! $conversation->first_response_at) {
            $conversation->forceFill(['first_response_at' => Date::now()])->save();
        }

        $channels->driver($conversation->inbox->channel_type)->send(
            $conversation->inbox,
            $conversation,
            new OutboundMessageData($message->body),
        );

        $message->setRelation('conversation', $conversation);
        event(new MessageSent($message));

        return response()->json($message);
    }
}
