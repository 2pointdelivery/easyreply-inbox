<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Support\Contracts\CurrentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InboxController
{
    public function index(Request $request, CurrentTeam $currentTeam): Response
    {
        $conversations = Conversation::query()
            ->with(['contact', 'inbox', 'messages' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Conversation $conversation) => $this->conversationSummary($conversation));

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
            ],
            // Lets the page subscribe to the team's real-time channel — see
            // resources/js/hooks/useSharedInboxChannel.js and BUILD_PROMPT.md §3.6.
            'team_id' => $currentTeam->resolve()?->id,
        ]);
    }

    public function show(Conversation $conversation, CurrentTeam $currentTeam, IntegrationManager $integrations): Response
    {
        $conversation->load(['contact', 'inbox', 'messages']);

        // AI drafts (status = draft) aren't shown in the sent-message
        // thread — the most recent one is surfaced separately so the
        // compose box can offer it for review/edit before sending.
        $sentMessages = $conversation->messages->where('status', Message::STATUS_SENT);
        $pendingDraft = $conversation->messages
            ->where('status', Message::STATUS_DRAFT)
            ->sortByDesc('created_at')
            ->first();

        return Inertia::render('Inbox/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'contact' => $this->contactSummary($conversation),
                'messages' => $sentMessages->values()->map(fn ($message) => $this->messageSummary($message)),
                'pending_draft' => $pendingDraft ? $this->messageSummary($pendingDraft) : null,
            ],
            'sidebar' => $this->sidebarData($conversation, $currentTeam, $integrations),
        ]);
    }

    /**
     * Optional integration context (BUILD_PROMPT.md §3.7) — every key is
     * empty/false when the corresponding integration isn't enabled for the
     * team, never required for the page to work.
     */
    protected function sidebarData(Conversation $conversation, CurrentTeam $currentTeam, IntegrationManager $integrations): array
    {
        $team = $currentTeam->resolve();

        if (! $team || ! $conversation->contact) {
            return ['customer_context' => [], 'incidents' => []];
        }

        $hubspot = $integrations->for($team, 'hubspot');
        $betterstack = $integrations->for($team, 'betterstack');

        return [
            'customer_context' => $hubspot?->fetchCustomerContext($conversation->contact) ?? [],
            'incidents' => $betterstack?->fetchIncidentStatus() ?? [],
        ];
    }

    protected function messageSummary(Message $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'body' => $message->body,
            'ai_generated' => $message->ai_generated,
            'status' => $message->status,
            'created_at' => $message->created_at,
        ];
    }

    protected function conversationSummary(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'contact' => $this->contactSummary($conversation),
            'preview' => Str::limit($conversation->messages->first()?->body ?? '', 140),
            'updated_at' => $conversation->updated_at,
        ];
    }

    protected function contactSummary(Conversation $conversation): ?array
    {
        if (! $conversation->contact) {
            return null;
        }

        return [
            'id' => $conversation->contact->id,
            'display_name' => $conversation->contact->display_name,
        ];
    }
}
