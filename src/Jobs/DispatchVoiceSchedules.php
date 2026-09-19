<?php

namespace Easyreply\Inbox\Jobs;

use Easyreply\Inbox\Models\CallLog;
use Easyreply\Inbox\Models\VoiceSchedule;
use Easyreply\Inbox\Voice\Data\OutboundCallData;
use Easyreply\Inbox\Voice\VoiceAgentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchVoiceSchedules implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(VoiceAgentManager $voices): void
    {
        $due = VoiceSchedule::query()
            ->where('status', VoiceSchedule::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->limit(config('shared-inbox.voice.outbound.rate_per_min', 10))
            ->get();

        foreach ($due as $schedule) {
            $team = $schedule->team;

            $call = CallLog::create([
                'team_id' => $schedule->team_id,
                'conversation_id' => $schedule->conversation_id,
                'direction' => CallLog::DIRECTION_OUTBOUND,
                'from_e164' => $team?->voice_number_override ?? config('shared-inbox.voice.shared_number_e164'),
                'to_e164' => $schedule->to_e164,
                'provider' => $team?->voice_driver ?? config('shared-inbox.voice.driver', 'null'),
                'provider_call_id' => 'pending-'.uniqid(),
                'status' => CallLog::STATUS_QUEUED,
                'started_at' => now(),
            ]);

            $providerCallId = $voices->driver($team?->voice_driver)->initiateOutbound(
                $call,
                new OutboundCallData($schedule->to_e164, null, $schedule->conversation_id)
            );

            $call->forceFill(['provider_call_id' => $providerCallId, 'status' => CallLog::STATUS_RINGING])->save();
            $schedule->forceFill(['status' => VoiceSchedule::STATUS_SENT])->save();
        }
    }
}
