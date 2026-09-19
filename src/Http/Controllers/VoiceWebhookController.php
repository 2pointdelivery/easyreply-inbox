<?php

namespace Easyreply\Inbox\Http\Controllers;

use Easyreply\Inbox\Jobs\ProcessCallPostActions;
use Easyreply\Inbox\Jobs\ProcessInboundCall;
use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\VoiceAgent;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stateless provider webhooks for voice agents (ElevenLabs, OpenAI
 * Realtime, Aircall, Vapi/Retell/Bland via generic-sip). Verifies the
 * provider signature first — rejects unsigned requests with 401 and writes
 * nothing — then dispatches queued jobs. Never handles audio.
 */
class VoiceWebhookController
{
    public function handle(Request $request, string $provider, VoiceAgentManager $voices): Response
    {
        $driver = $this->resolve($provider, $voices);

        abort_unless($driver->verifyWebhookSignature($request), 401);

        $payload = $request->all();

        if (! $driver->worthProcessing($payload)) {
            return response()->noContent();
        }

        ProcessInboundCall::dispatch($provider, $payload, $this->teamIdFor($request));

        return response()->noContent();
    }

    public function status(Request $request, string $provider, VoiceAgentManager $voices): Response
    {
        $driver = $this->resolve($provider, $voices);

        abort_unless($driver->verifyWebhookSignature($request), 401);

        $payload = $request->all();
        $callId = (string) ($payload['call_id'] ?? $payload['provider_call_id'] ?? $payload['data']['conversation_id'] ?? '');

        if ($callId !== '') {
            ProcessCallPostActions::dispatch($provider, $callId, $payload);
        }

        return response()->noContent();
    }

    public function transfer(Request $request, string $provider, VoiceAgentManager $voices): Response
    {
        $driver = $this->resolve($provider, $voices);

        abort_unless($driver->verifyWebhookSignature($request), 401);

        $validated = $request->validate([
            'provider_call_id' => ['required', 'string'],
            'transfer_target' => ['required', 'string'],
        ]);

        $call = CallLog::withoutGlobalScopes()->where('provider_call_id', $validated['provider_call_id'])->first();

        if (! $call) {
            throw new NotFoundHttpException;
        }

        $ok = $driver->transferCall($validated['provider_call_id'], $validated['transfer_target']);

        $call->forceFill([
            'status' => $ok ? CallLog::STATUS_TRANSFERRED : $call->status,
            'transfer_outcome' => $ok ? 'transferred to '.$validated['transfer_target'] : 'transfer failed',
        ])->save();

        return response()->noContent();
    }

    protected function resolve(string $provider, VoiceAgentManager $voices)
    {
        try {
            return $voices->driver($provider);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException;
        }
    }

    protected function teamIdFor(Request $request): ?int
    {
        // Single shared number v1: IVR team-select posts team_id, or the
        // agent prompt resolves it later. Null = resolve from agent default.
        if ($request->filled('team_id')) {
            return (int) $request->input('team_id');
        }

        $agentId = $request->input('agent_id');

        if ($agentId) {
            return VoiceAgent::withoutGlobalScopes()->whereKey($agentId)->value('team_id');
        }

        return null;
    }
}
