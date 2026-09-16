<?php

namespace Easyreply\Inbox\Integrations\Contracts;

use Easyreply\Inbox\Integrations\Data\ExternalLink;
use Easyreply\Inbox\Models\Contact;
use Easyreply\Inbox\Models\Conversation;

/**
 * Not every integration implements every method meaningfully — Linear isn't
 * a CRM, HubSpot isn't an issue tracker, Betterstack is neither. An
 * implementation that doesn't support a capability just returns the "no
 * data" value (null / empty array), never throws.
 */
interface Integration
{
    /**
     * Create or attach an external issue for a conversation (e.g. a Linear
     * issue for a recurring bug). Returns null if this integration doesn't
     * support issue linking, or the call failed.
     */
    public function linkExternalIssue(Conversation $conversation, array $attributes): ?ExternalLink;

    /**
     * Pull CRM context for a contact (company, deal, past tickets) to show
     * alongside a conversation. Returns [] if this integration doesn't
     * support CRM context, or none was found.
     */
    public function fetchCustomerContext(Contact $contact): array;

    /**
     * Current unresolved incidents from a status page, if this integration
     * surfaces one. Returns [] if none, or unsupported.
     */
    public function fetchIncidentStatus(): array;
}
