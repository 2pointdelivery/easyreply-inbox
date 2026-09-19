<?php

namespace Easyreply\Inbox\Support;

use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Inbox;

/**
 * Guest authentication for the public website widget. Visitors are not
 * logged-in users: an inbox exposes `config.widget_enabled` plus a public
 * `config.widget_token` (rotatable, like an Intercom app id), and each
 * visitor proves ownership with a per-session `visitor_token` stored as a
 * `widget` channel identity (`visitor:{token}`).
 */
class WidgetAuth
{
    public function inbox(int $inboxId): ?Inbox
    {
        return Inbox::withoutGlobalScopes()->whereKey($inboxId)->first();
    }

    public function tokenValid(?Inbox $inbox, ?string $token): bool
    {
        if (! $inbox || ! ($inbox->config['widget_enabled'] ?? false)) {
            return false;
        }

        $expected = (string) ($inbox->config['widget_token'] ?? '');

        if ($expected === '' || ! is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function contactForVisitor(Inbox $inbox, string $visitorToken): ?Contact
    {
        return Contact::withoutGlobalScopes()
            ->where('team_id', $inbox->team_id)
            ->whereHas('channelIdentities', fn ($query) => $query
                ->where('channel_type', 'widget')
                ->where('external_id', $this->visitorIdentity($visitorToken)))
            ->first();
    }

    public function visitorIdentity(string $visitorToken): string
    {
        return 'visitor:'.$visitorToken;
    }
}
