<?php

namespace Easyreply\Inbox\Support;

use Carbon\CarbonInterface;
use Easyreply\Inbox\Models\SlaPolicy;
use Illuminate\Support\Facades\Date;

/**
 * Computes sla_due_at for a conversation from the team's SlaPolicy matching
 * its priority (see BUILD_PROMPT.md §3.2). Returns null when the team has no
 * policy for that priority — SLA tracking stays fully optional.
 */
class SlaCalculator
{
    public function dueAt(int $teamId, string $priority): ?CarbonInterface
    {
        $policy = SlaPolicy::query()
            ->where('team_id', $teamId)
            ->where('priority', $priority)
            ->first();

        if (! $policy) {
            return null;
        }

        return Date::now()->addMinutes($policy->first_response_minutes);
    }
}
