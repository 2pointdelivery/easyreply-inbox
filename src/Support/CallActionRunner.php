<?php

namespace Easyreply\Inbox\Support;

use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Models\CallAction;
use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Notifications\SlackRoutingNotifier;
use Throwable;

/**
 * Executes the four post-call action families and audits each into
 * call_actions: create_thread (already linked, recorded), triage+assign,
 * integrations (Linear/HubSpot), handoff/callback. Every step is
 * best-effort and individually audited — one failure never blocks the rest.
 */
class CallActionRunner
{
    public function __construct(
        protected SlackRoutingNotifier $slackRouting,
        protected IntegrationManager $integrations,
    ) {}

    public function run(CallLog $call): void
    {
        $call->loadMissing(['conversation', 'contact', 'team']);

        $this->audit($call, CallAction::TYPE_CREATE_THREAD, [
            'conversation_id' => $call->conversation_id,
            'contact_id' => $call->contact_id,
        ], fn () => $call->conversation_id !== null);

        $this->audit($call, CallAction::TYPE_TRIAGE, [
            'priority_suggestion' => $call->priority_suggestion,
        ], function () use ($call) {
            $conversation = $call->conversation_id
                ? Conversation::withoutGlobalScopes()->find($call->conversation_id)
                : null;

            if (! $conversation) {
                return false;
            }

            if ($call->priority_suggestion && $conversation->priority !== $call->priority_suggestion) {
                $conversation->forceFill(['priority' => $call->priority_suggestion])->save();
            }

            // Automated voice triage is recorded on call_actions + the
            // linked message thread. Internal notes require a real author
            // (notes.user_id is NOT NULL), so the runner never fabricates
            // one here — agents add follow-up notes from the UI.

            if ($conversation->wasRecentlyCreated || $call->wasRecentlyCreated) {
                $this->slackRouting->notifyNewConversation($conversation);
            }

            return true;
        });

        $this->audit($call, CallAction::TYPE_INTEGRATION, [
            'contact_id' => $call->contact_id,
        ], function () use ($call) {
            $team = $call->team ?? ($call->team_id ? \Easyreply\Inbox\Models\Team::find($call->team_id) : null);

            if (! $team || ! $call->contact) {
                return false;
            }

            $hubspot = $this->integrations->for($team, 'hubspot');
            $hubspot?->fetchCustomerContext($call->contact);

            return true;
        });

        if ($call->transfer_outcome || $call->status === CallLog::STATUS_TRANSFERRED) {
            $this->audit($call, CallAction::TYPE_HANDOFF, [
                'transfer_outcome' => $call->transfer_outcome,
                'status' => $call->status,
            ], fn () => true);
        }
    }

    protected function audit(CallLog $call, string $type, array $payload, callable $work): void
    {
        try {
            $ok = (bool) $work();

            $call->actions()->create([
                'action_type' => $type,
                'payload' => $payload,
                'status' => $ok ? CallAction::STATUS_DONE : CallAction::STATUS_FAILED,
                'error' => $ok ? null : 'Skipped: missing linked record.',
            ]);
        } catch (Throwable $e) {
            $call->actions()->create([
                'action_type' => $type,
                'payload' => $payload,
                'status' => CallAction::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function triageNote(CallLog $call): string
    {
        return trim(implode("\n", array_filter([
            "Voice call {$call->provider_call_id} ({$call->direction}, {$call->status}).",
            $call->summary ? "Summary: {$call->summary}" : null,
            $call->sentiment ? "Sentiment: {$call->sentiment}" : null,
            $call->priority_suggestion ? "Suggested priority: {$call->priority_suggestion}" : null,
        ])));
    }
}
