<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Models\Note;
use Easyreply\Inbox\Models\Team;
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
        $conversation->load(['contact', 'inbox', 'messages.attachments', 'labels', 'notes.author']);

        // AI drafts (status = draft) aren't shown in the sent-message
        // thread — the most recent one is surfaced separately so the
        // compose box can offer it for review/edit before sending.
        $sentMessages = $conversation->messages->where('status', Message::STATUS_SENT);
        $pendingDraft = $conversation->messages
            ->where('status', Message::STATUS_DRAFT)
            ->sortByDesc('created_at')
            ->first();

        $team = $currentTeam->resolve();

        return Inertia::render('Inbox/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'sla_due_at' => $conversation->sla_due_at,
                'contact' => $this->contactSummary($conversation),
                'messages' => $sentMessages->values()->map(fn ($message) => $this->messageSummary($message)),
                'pending_draft' => $pendingDraft ? $this->messageSummary($pendingDraft) : null,
                'labels' => $conversation->labels->map(fn ($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                ]),
                'notes' => $conversation->notes->map(fn (Note $note) => $this->noteSummary($note)),
            ],
            'sidebar' => $this->sidebarData($conversation, $currentTeam, $integrations),
            'team_members' => $team ? $this->teamMemberOptions($team) : [],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: ?string}>
     */
    protected function teamMemberOptions(Team $team): array
    {
        return $team->users()->get(['users.id', 'users.name'])
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    protected function noteSummary(Note $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'mentioned_user_ids' => $note->mentioned_user_ids,
            'author' => $note->author?->name,
            'created_at' => $note->created_at,
        ];
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
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'url' => $attachment->url(),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ]),
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
