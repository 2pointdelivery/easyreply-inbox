<?php

namespace Easyreply\Inbox\Support;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Inbox;
use Easyreply\Inbox\Models\Message;
use Easyreply\Inbox\Models\VoiceAgent;
use Easyreply\Inbox\Voice\Data\InboundCallData;

/**
 * Turns a normalized InboundCallData into linked CallLog + Contact +
 * Conversation + Message rows. Mirrors ProcessInboundMessage's
 * find-or-create flow, but for voice (E.164 identities, single shared
 * number routing, no audio anywhere).
 */
class CallLinker
{
    public function link(InboundCallData $data, ?int $teamId = null, ?VoiceAgent $agent = null): CallLog
    {
        $teamId = $teamId ?? $agent?->team_id;

        // Idempotency: provider retries / status-callback replays reuse the
        // same provider_call_id and must not create duplicate logs.
        $existing = CallLog::withoutGlobalScopes()->where('provider_call_id', $data->providerCallId)->first();

        if ($existing) {
            $this->refreshProgress($existing, $data);

            return $existing;
        }

        $contact = $teamId ? $this->findOrCreateContact($teamId, $data) : null;
        $inbox = $teamId ? $this->findOrCreateVoiceInbox($teamId) : null;
        $conversation = ($teamId && $inbox && $contact)
            ? $this->findOrCreateConversation($teamId, $inbox, $contact, $data)
            : null;

        $call = CallLog::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'voice_agent_id' => $agent?->id,
            'inbox_id' => $inbox?->id,
            'conversation_id' => $conversation?->id,
            'contact_id' => $contact?->id,
            'direction' => $data->direction,
            'from_e164' => $data->fromE164,
            'to_e164' => $data->toE164,
            'provider' => $data->provider,
            'provider_call_id' => $data->providerCallId,
            'status' => $data->status,
            'started_at' => now(),
            'duration_seconds' => $data->durationSeconds,
            'transcript' => $this->redactPii($data->transcript),
            'summary' => $data->summary,
            'sentiment' => $data->sentiment,
            'priority_suggestion' => $this->priorityFor($data->sentiment, $data->transcript),
            'raw_payload' => $this->scrub($data->rawPayload),
        ]);

        if ($conversation) {
            $conversation->messages()->create([
                'direction' => $data->direction === CallLog::DIRECTION_OUTBOUND
                    ? Message::DIRECTION_OUTBOUND
                    : Message::DIRECTION_INBOUND,
                'channel' => 'voice',
                'external_id' => $data->providerCallId,
                'sender_type' => $contact ? Contact::class : null,
                'sender_id' => $contact?->id,
                'body' => $this->threadBody($data),
                'raw_payload' => $this->scrub($data->rawPayload),
                'status' => Message::STATUS_SENT,
            ]);
        }

        return $call;
    }

    protected function refreshProgress(CallLog $call, InboundCallData $data): CallLog
    {
        $call->forceFill([
            'status' => $data->status,
            'duration_seconds' => $data->durationSeconds ?? $call->duration_seconds,
            'transcript' => $this->redactPii($data->transcript) ?? $call->transcript,
            'summary' => $data->summary ?? $call->summary,
            'sentiment' => $data->sentiment ?? $call->sentiment,
        ])->save();

        return $call;
    }

    protected function findOrCreateContact(int $teamId, InboundCallData $data): Contact
    {
        $caller = $data->direction === CallLog::DIRECTION_OUTBOUND ? $data->toE164 : $data->fromE164;

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereHas('channelIdentities', fn ($q) => $q->where('channel_type', 'voice')->where('external_id', $caller))
            ->first();

        if ($contact) {
            return $contact;
        }

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'display_name' => $caller ?: 'Unknown caller',
        ]);

        if ($caller) {
            $contact->channelIdentities()->create([
                'channel_type' => 'voice',
                'external_id' => $caller,
            ]);
        }

        return $contact;
    }

    protected function findOrCreateVoiceInbox(int $teamId): Inbox
    {
        return Inbox::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $teamId, 'channel_type' => 'voice'],
            ['name' => 'Voice', 'config' => ['number' => config('shared-inbox.voice.shared_number_e164')], 'is_active' => true]
        );
    }

    protected function findOrCreateConversation(int $teamId, Inbox $inbox, Contact $contact, InboundCallData $data): Conversation
    {
        $open = Conversation::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('inbox_id', $inbox->id)
            ->where('contact_id', $contact->id)
            ->whereNotIn('status', ['closed'])
            ->first();

        if ($open) {
            return $open;
        }

        return Conversation::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'inbox_id' => $inbox->id,
            'contact_id' => $contact->id,
            'subject' => 'Call '.($data->fromE164 ?: $data->toE164),
            'status' => 'open',
            'priority' => $this->priorityFor($data->sentiment, $data->transcript) ?? 'normal',
        ]);
    }

    public function priorityFor(?string $sentiment, ?string $transcript): ?string
    {
        $haystack = strtolower((string) $sentiment.' '.(string) $transcript);

        if (str_contains($haystack, 'urgent') || str_contains($haystack, 'emergency') || str_contains($haystack, 'angry') || str_contains($haystack, 'furious') || str_contains($haystack, 'negative')) {
            return 'high';
        }

        if (str_contains($haystack, 'positive') || str_contains($haystack, 'thank')) {
            return 'low';
        }

        return null;
    }

    protected function threadBody(InboundCallData $data): string
    {
        $parts = array_filter([
            $data->summary ? "Summary: {$data->summary}" : null,
            $data->transcript ? "Transcript: {$data->transcript}" : null,
        ]);

        return implode("\n\n", $parts) ?: "Voice call ({$data->status})";
    }

    /**
     * Best-effort PII redaction before persistence (card-like digit runs).
     */
    public function redactPii(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        return (string) preg_replace('/\b\d{13,19}\b/', '[redacted-card]', $text);
    }

    /**
     * Strip anything that could carry secrets or audio references before
     * persisting raw_payload. Never store recording URLs or audio blobs.
     */
    public function scrub(array $payload): array
    {
        $dropKeys = ['recording_url', 'recordingUrl', 'audio_url', 'audioUrl', 'audio', 'recording', 'api_key', 'apiKey', 'authorization'];

        foreach ($dropKeys as $key) {
            unset($payload[$key]);
            if (isset($payload['data']) && is_array($payload['data'])) {
                unset($payload['data'][$key]);
            }
            if (isset($payload['call']) && is_array($payload['call'])) {
                unset($payload['call'][$key]);
            }
        }

        return $payload;
    }
}
