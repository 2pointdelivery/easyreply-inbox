<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Events\MessageReceived;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Notifications\SlackRoutingNotifier;
use Easyreply\Inbox\Support\WidgetAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public guest chat for the website widget (stateless, token-authed, no
 * session — see routes/api.php). Every endpoint verifies the inbox's
 * widget token first and writes nothing on failure.
 */
class WidgetChatController
{
    public function __construct(
        protected WidgetAuth $auth,
        protected SlackRoutingNotifier $slackRouting,
    ) {}

    public function start(Request $request, Inbox $inbox): JsonResponse
    {
        abort_unless($this->auth->tokenValid($inbox, $this->token($request)), 401);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $contact = $this->findOrCreateContact($inbox, $validated);
        $visitorToken = Str::random(40);

        $contact->channelIdentities()->create([
            'channel_type' => 'widget',
            'external_id' => $this->auth->visitorIdentity($visitorToken),
        ]);

        $conversation = $this->findOrCreateConversation($inbox, $contact);

        if ($conversation->wasRecentlyCreated) {
            $this->slackRouting->notifyNewConversation($conversation);
        }

        return response()->json([
            'visitor_token' => $visitorToken,
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function index(Request $request, Inbox $inbox): JsonResponse
    {
        $conversation = $this->visitorConversation($request, $inbox);
        abort_unless($conversation, 401);

        $messages = $conversation->messages()
            ->where('status', Message::STATUS_SENT)
            ->orderBy('created_at')
            ->when($request->filled('after_id'), fn ($query) => $query->where('id', '>', (int) $request->input('after_id')))
            ->limit(100)
            ->get(['id', 'direction', 'body', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Inbox $inbox): JsonResponse
    {
        $conversation = $this->visitorConversation($request, $inbox);
        abort_unless($conversation, 401);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $contact = $conversation->contact;

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_INBOUND,
            'channel' => 'widget',
            'external_id' => (string) Str::uuid(),
            'sender_type' => Contact::class,
            'sender_id' => $contact?->id,
            'body' => trim($validated['body']),
            'status' => Message::STATUS_SENT,
        ]);

        $message->setRelation('conversation', $conversation);
        event(new MessageReceived($message));

        return response()->json(['message' => $message->only(['id', 'body', 'created_at'])], 201);
    }

    public function status(Request $request, Inbox $inbox): JsonResponse
    {
        abort_unless($this->auth->tokenValid($inbox, $this->token($request)), 401);

        return response()->json([
            'widget_enabled' => true,
            'display_number' => config('shared-inbox.voice.shared_number_e164'),
            'voice_configured' => config('shared-inbox.voice.driver', 'null') !== 'null',
            'callbacks_enabled' => (bool) config('shared-inbox.voice.outbound.widget_callbacks', true),
        ]);
    }

    protected function token(Request $request): ?string
    {
        return $request->input('token') ?? $request->header('X-Widget-Token');
    }

    protected function visitorConversation(Request $request, Inbox $inbox): ?Conversation
    {
        if (! $this->auth->tokenValid($inbox, $this->token($request))) {
            return null;
        }

        $visitorToken = (string) ($request->input('visitor_token') ?? $request->header('X-Visitor-Token'));

        if ($visitorToken === '') {
            return null;
        }

        $contact = $this->auth->contactForVisitor($inbox, $visitorToken);

        if (! $contact) {
            return null;
        }

        return Conversation::withoutGlobalScopes()
            ->where('team_id', $inbox->team_id)
            ->where('inbox_id', $inbox->id)
            ->where('contact_id', $contact->id)
            ->whereNotIn('status', ['closed'])
            ->latest('created_at')
            ->first();
    }

    protected function findOrCreateContact(Inbox $inbox, array $validated): Contact
    {
        foreach (['email', 'phone'] as $key) {
            if (empty($validated[$key])) {
                continue;
            }

            $match = Contact::withoutGlobalScopes()
                ->where('team_id', $inbox->team_id)
                ->whereHas('channelIdentities', fn ($query) => $query
                    ->where('channel_type', 'widget')
                    ->where('external_id', $validated[$key]))
                ->first();

            if ($match) {
                return $match;
            }
        }

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $inbox->team_id,
            'display_name' => $validated['name'] ?? 'Website visitor',
        ]);

        foreach (['email', 'phone'] as $key) {
            if (! empty($validated[$key])) {
                $contact->channelIdentities()->create([
                    'channel_type' => 'widget',
                    'external_id' => $validated[$key],
                ]);
            }
        }

        return $contact;
    }

    protected function findOrCreateConversation(Inbox $inbox, Contact $contact): Conversation
    {
        $open = Conversation::withoutGlobalScopes()
            ->where('team_id', $inbox->team_id)
            ->where('inbox_id', $inbox->id)
            ->where('contact_id', $contact->id)
            ->whereNotIn('status', ['closed'])
            ->first();

        if ($open) {
            return $open;
        }

        return Conversation::withoutGlobalScopes()->create([
            'team_id' => $inbox->team_id,
            'inbox_id' => $inbox->id,
            'contact_id' => $contact->id,
            'subject' => 'Website chat with '.($contact->display_name ?? 'visitor'),
            'status' => 'open',
        ]);
    }
}
