<?php

namespace Easyreply\Inbox\Http\Controllers\Concerns;

use Easyreply\Inbox\Models\Team;
use Illuminate\Http\Request;

/**
 * Label and SLA policy management is team-admin-only (BUILD_PROMPT.md §6,
 * "Label & SLA management (team admin only)"). Every other settings screen
 * in the package is open to any team member, matching the existing
 * IntegrationSettingsController/McpSettingsController behavior.
 */
trait AuthorizesTeamRole
{
    protected function ensureTeamAdmin(Request $request, Team $team): void
    {
        $role = $request->user()?->roleOnTeam($team);

        abort_unless(in_array($role, ['owner', 'admin'], true), 403, 'Only team owners/admins can manage this.');
    }
}
