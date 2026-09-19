<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\VoiceSchedule;
use Easyreply\Inbox\Support\WidgetAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Call me back" requests from the website widget. A guest submits their
 * phone number and the package queues a VoiceSchedule the AI voice agent
 * dials (see DispatchVoiceSchedules + the `voice:dispatch-voice-schedules`
 * command). Transcript-only once connected — the strict no-audio policy
 * applies to callbacks exactly like any other call.
 */
class WidgetCallController
{
    public function __construct(
        protected WidgetAuth $auth,
    ) {}

    public function store(Request $request, Inbox $inbox): JsonResponse
    {
        abort_unless(config('shared-inbox.voice.outbound.widget_callbacks', true), 403, 'Call requests are disabled.');

        $conversation = $this->visitorConversation($request, $inbox);
        abort_unless($conversation, 401);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'topic' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule = VoiceSchedule::withoutGlobalScopes()->create([
            'team_id' => $inbox->team_id,
            'to_e164' => $validated['phone'],
            'scheduled_at' => now()->addMinutes(5),
            'status' => VoiceSchedule::STATUS_PENDING,
            'conversation_id' => $conversation->id,
            'meta' => array_filter(['topic' => $validated['topic'] ?? null, 'source' => 'widget']),
        ]);

        return response()->json([
            'status' => 'scheduled',
            'scheduled_at' => $schedule->scheduled_at,
            'display_number' => config('shared-inbox.voice.shared_number_e164'),
        ], 201);
    }

    protected function visitorConversation(Request $request, Inbox $inbox): ?Conversation
    {
        $token = $request->input('token') ?? $request->header('X-Widget-Token');

        if (! $this->auth->tokenValid($inbox, $token)) {
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
}
