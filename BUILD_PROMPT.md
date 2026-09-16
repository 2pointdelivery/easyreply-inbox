# Build Prompt: Laravel Shared Inbox Package (EasyReply-style)

You are building a **self-hosted, installable Laravel package** that reimplements the
core product experience of [EasyReply](https://easyreply.io) — an AI-powered shared
inbox that unifies email, Slack, WhatsApp, and Instagram into one team inbox, with
AI-drafted replies and integrations into Linear, HubSpot, and Betterstack, plus
MCP-based tool access for AI agents via Composio.dev.

This file is a complete, standalone spec. Do not assume access to the original
EasyReply source — it is closed-source SaaS. Everything you need to build from is here.

---

## 1. Product summary (what we're rebuilding)

EasyReply solves inbox fragmentation for support teams: instead of jumping between
Gmail, Slack, Instagram, and a CRM, a team works from one shared inbox. Core
capabilities to replicate:

- **Unified inbox** across email, Slack, WhatsApp, and Instagram DMs.
- **Conversation collaboration**: assign an owner, @mention teammates, leave internal
  notes visible only to the team (not the customer), alongside the customer thread.
- **Organization**: labels, priorities, and SLA tracking per conversation.
- **Routing**: new tickets and replies can be routed to Slack channels so the right
  people see updates without opening the inbox.
- **Issue linking**: link a conversation to a Linear or GitHub issue for recurring bugs,
  and notify the customer/agent when it ships.
- **AI-drafted replies**: an AI can draft a suggested reply for an agent to review,
  edit, and send.
- **MCP tool access**: AI agents can securely access support data and act on the
  team's behalf through OAuth-connected tools (via Composio.dev), not a bespoke
  integration per tool.
- **Real-time**: new messages and conversation updates appear instantly for everyone
  viewing the inbox, no manual refresh.

Non-goals for this package: billing/subscriptions, public marketing site, native
mobile apps. This is OSS infrastructure a Laravel app installs — not a hosted SaaS
clone with its own billing.

---

## 2. Project identity

- **Package name**: placeholder `vendor/laravel-shared-inbox` — **confirm the actual
  Composer vendor/package name and PHP namespace with the user before publishing**;
  use `Vendor\\SharedInbox` as the placeholder root namespace throughout scaffolding.
- **Target**: PHP 8.2+, Laravel 11+.
- **Scaffolding**: use Spatie's `laravel-package-tools` conventions (a `PackageServiceProvider`
  extending `Spatie\LaravelPackageTools\Package`, `composer.json` with `extra.laravel.providers`
  auto-discovery, `:vendor:publish` tags for config/migrations/assets).
- **Frontend**: Inertia.js + React (matches the real product), Tailwind CSS for styling.
- **Package manager for JS assets**: ship a `resources/js` tree the host app's Vite
  config can alias in (document the `vite.config.js` addition required), rather than
  bundling a separate build step inside the package.

---

## 3. Architecture

### 3.1 Multi-tenancy

Multi-tenancy is a first-class, built-in concept (not left to the host app):

- `Team` (a.k.a. workspace) — the top-level tenant boundary. Everything else
  (inboxes, conversations, labels, integrations) belongs to exactly one `Team`.
- `User` belongs to many `Team`s via a `team_user` pivot with a `role` column
  (e.g. `owner`, `admin`, `agent`).
- Every query-scoped model uses a `BelongsToTeam` trait/global scope keyed off the
  currently active team (resolved from session/request context — provide a
  `CurrentTeam` contract/resolver the host app can bind, defaulting to "user's first
  team" if unset).
- A team switcher UI component lets a user with multiple teams switch active team.

### 3.2 Core domain model

- `Inbox` — one per connected channel (e.g. a specific email address, Slack workspace
  connection, WhatsApp number, Instagram account). Belongs to a `Team`. Stores
  `channel_type` and channel-specific `config`/`credentials` (encrypted JSON).
- `Conversation` — a thread with a customer. Belongs to an `Inbox` (and transitively a
  `Team`). Has `status` (open/pending/closed/snoozed), `priority`, `assignee_id`
  (nullable, a `User`), SLA fields (`sla_due_at`, `first_response_at`,
  `resolved_at`), and a `subject`/`preview` for list views.
- `Message` — belongs to a `Conversation`. `direction` (inbound/outbound),
  `channel` (denormalized from inbox for convenience), `body` (rendered), `raw_payload`
  (original provider payload, JSON), `ai_generated` (bool), `sender` (polymorphic:
  a `User` for outbound-by-agent, or a lightweight `Contact` for inbound).
- `Contact` — the external person (customer) on the other end of a conversation,
  scoped to `Team`, with per-channel identifiers (email address, Slack user id,
  WhatsApp number, Instagram handle) stored in a `contact_channel_identities` table
  so the same person can be recognized across channels where possible.
- `Note` — an internal note attached to a `Conversation`, authored by a `User`, never
  sent to the customer. Supports @mentions (store mentioned `user_id`s for
  notifications).
- `Label` and `conversation_label` pivot — team-scoped labels, many-to-many with
  conversations.
- `SlaPolicy` — team-scoped rules (e.g. "first response within 1 hour for priority
  high") used to compute `sla_due_at` on conversation creation/priority change.
- `McpConnection` — belongs to `Team`. Stores the Composio account/connection id,
  the app/tool being connected (e.g. `gmail`, `linear`), granted scopes, and
  encrypted OAuth tokens (if Composio requires local storage — prefer storing only
  Composio's connection reference and letting Composio hold the actual tokens; the
  package should treat Composio as the token custodian where possible).
- `IntegrationSetting` — team-scoped, one row per optional integration
  (`linear`, `hubspot`, `betterstack`), storing enabled flag + encrypted API
  credentials/config.

### 3.3 Channel abstraction

Define a `ChannelDriver` contract:

```php
interface ChannelDriver
{
    public function normalizeInbound(array $payload): InboundMessageData;
    public function send(Conversation $conversation, OutboundMessageData $message): void;
    public function verifyWebhookSignature(Request $request): bool;
}
```

Register drivers through a Laravel `Manager` (`ChannelManager extends Manager`) keyed
by `channel_type`, resolved from `config('shared-inbox.channels')`. Ship four
first-party drivers, each independently enable-able via config:

- **Email**: inbound via webhook parsing compatible with Postmark/SES/Mailgun/
  Mailgun-style inbound-parse payload shapes (support at least one concretely,
  document the contract for adding others); outbound via Laravel `Mail` /SMTP.
  Must handle threading (In-Reply-To/References headers → match existing
  `Conversation`).
- **Slack**: inbound via Slack Events API webhook (message events in a connected
  channel or DM); outbound via Slack Web API (`chat.postMessage`). Also implements
  the "route new tickets/replies to a Slack channel" notification feature
  independently of Slack-as-a-channel (this is a notification, not just a channel
  driver responsibility — implement as a separate `SlackRoutingNotifier` listening to
  conversation events).
- **WhatsApp**: inbound/outbound via WhatsApp Business Cloud API (Meta Graph API),
  webhook verification per Meta's challenge/response handshake.
- **Instagram**: inbound/outbound DMs via Meta Graph API (Instagram Messaging),
  same webhook verification family as WhatsApp/Meta.

Each driver's webhook route registers under `routes/web.php` (or `api.php`) as
`shared-inbox/webhooks/{channel}`, guarded by that driver's `verifyWebhookSignature`,
and rate-limited.

### 3.4 AI drafting (provider-agnostic)

Define an `AiReplyDriver` contract:

```php
interface AiReplyDriver
{
    public function draftReply(Conversation $conversation): DraftReplyData;
}
```

- Resolve the active driver via config (`config('shared-inbox.ai.driver')`), per-team
  override optional (store on `Team` or a settings table if per-team model choice is
  wanted — default to global config for v1, note per-team as a documented extension
  point).
- Ship a `NullAiDriver` (no-op, returns "AI drafting not configured") as the safe
  default, plus **reference implementations** for OpenAI and Anthropic (each a thin
  adapter class using that provider's SDK/HTTP API, reading the conversation's message
  history and producing a draft). Do not hardcode a "best" provider — document both as
  equally-supported examples and let host apps write their own adapter for anything
  else by implementing the same contract.
- Drafts are stored as a `Message` with `ai_generated = true` and a `status` of
  `draft` (not yet sent) — the agent UI shows it in the compose box for review/edit,
  and sending it transitions it to a normal outbound message.

### 3.5 MCP via Composio (OAuth-based tool access)

- Use Composio.dev as the connection/auth broker rather than building bespoke OAuth
  flows per tool. Flow: team admin clicks "Connect" for a tool (e.g. Gmail, Linear,
  Slack) in package settings UI → package calls Composio's API to initiate a
  connection → user completes OAuth on the provider's site via Composio's hosted
  flow → Composio redirects back to a package-owned callback route
  (`shared-inbox/mcp/callback`) → package stores the resulting `McpConnection`
  record (Composio connection id + granted tool scopes), not raw provider tokens.
- Provide an `McpToolProvider` service wrapping Composio's API to: list available
  tools/apps, initiate a connection, list a team's active connections, execute a
  tool action on behalf of a team (used by the AI drafting flow or an agent chat
  feature) within the scopes the team has authorized.
- This is the mechanism that lets AI agents "securely access custom data sources" —
  design it so an `AiReplyDriver` implementation *can* call into `McpToolProvider`
  to pull context (e.g. "look up this customer's Linear issues") while drafting,
  but keep that optional/composable rather than baked into the base contract.

### 3.6 Real-time (broadcasting-agnostic)

- Package defines Laravel broadcast events implementing `ShouldBroadcast`:
  `MessageReceived`, `MessageSent`, `ConversationUpdated`, `ConversationAssigned`.
  Broadcast on a private channel per team (`shared-inbox.team.{teamId}`) and/or per
  conversation (`shared-inbox.conversation.{conversationId}`).
- The package **does not** require or configure a specific broadcaster (no bundled
  Reverb setup). It relies entirely on the host app's existing `config/broadcasting.php`
  driver (Reverb, Pusher, Ably, log, null). Document in the README exactly what the
  host app must configure (broadcaster driver + `BROADCAST_CONNECTION` +
  `resources/js` Echo client setup) to get live updates — package ships the events
  and the React hook (`useSharedInboxChannel`) that subscribes via Laravel Echo, but
  Echo/broadcaster wiring itself is the host app's responsibility.
- Without broadcasting configured, the UI still functions via polling/refresh
  fallback (document this graceful-degradation behavior).

### 3.7 Optional integrations (Linear, HubSpot, Betterstack)

Define a thin `Integration` contract:

```php
interface Integration
{
    public function linkExternalIssue(Conversation $conversation, array $attributes): ExternalLink;
    public function fetchCustomerContext(Contact $contact): array;
    public function fetchIncidentStatus(): array;
}
```

Not every integration implements every method meaningfully — implementations may
no-op methods that don't apply (e.g. Betterstack doesn't link issues; implement only
`fetchIncidentStatus`). Each integration is:

- Config-gated (`config('shared-inbox.integrations.linear.enabled')`, etc.).
- Backed by an `IntegrationSetting` row for credentials.
- **Linear**: link a conversation to a Linear issue (create or attach existing),
  surface issue status changes back onto the conversation (webhook or poll).
- **HubSpot**: pull CRM context (company, deal, past tickets) for the contact,
  shown in a sidebar on the conversation view.
- **Betterstack**: surface current incident/status-page state (e.g. banner on the
  inbox if there's an active incident relevant to support volume).

These must not be required for the package's core inbox functionality to work.

---

## 4. Package structure

```
src/
  SharedInboxServiceProvider.php
  Models/
    Team.php, User.php (trait/contract, not a full model — host app owns User), Inbox.php,
    Conversation.php, Message.php, Contact.php, ContactChannelIdentity.php, Note.php,
    Label.php, SlaPolicy.php, McpConnection.php, IntegrationSetting.php
  Channels/
    Contracts/ChannelDriver.php
    ChannelManager.php
    EmailChannelDriver.php, SlackChannelDriver.php, WhatsAppChannelDriver.php,
    InstagramChannelDriver.php
  Ai/
    Contracts/AiReplyDriver.php
    AiDriverManager.php
    NullAiDriver.php, OpenAiReplyDriver.php, AnthropicReplyDriver.php
  Mcp/
    McpToolProvider.php
    ComposioClient.php
  Integrations/
    Contracts/Integration.php
    LinearIntegration.php, HubSpotIntegration.php, BetterstackIntegration.php
  Events/
    MessageReceived.php, MessageSent.php, ConversationUpdated.php, ConversationAssigned.php
  Http/
    Controllers/ (Inbox, Conversation, Message, Webhook per channel, Mcp, Settings)
    Middleware/ (VerifyWebhookSignature per channel or unified)
    Requests/
  Notifications/
    SlackRoutingNotifier.php (or a Notification class)
  Support/
    CurrentTeam.php (contract + default resolver)
config/
  shared-inbox.php
database/
  migrations/ (one per table, see §5)
resources/
  js/
    Pages/ (Inertia pages: Inbox/Index, Inbox/Show, Settings/Channels, Settings/Team, etc.)
    Components/ (ConversationList, MessageThread, ComposeBox, LabelPicker, TeamSwitcher, ...)
    hooks/useSharedInboxChannel.js
  views/
    shared-inbox/app.blade.php (Inertia root view)
routes/
  web.php
  api.php (webhooks)
tests/
  Feature/
  Unit/
README.md
composer.json
```

Service provider responsibilities: register config (mergeable, publishable),
publish migrations, publish/compile frontend assets or document the host-app Vite
alias, register the `ChannelManager`, `AiDriverManager`, `McpToolProvider`, and
`Integration` bindings as singletons, register routes, register the `CurrentTeam`
binding with a sensible default and a way for the host app to override it.

---

## 5. Data model / migrations (explicit table list)

- `teams` — id, name, timestamps.
- `team_user` — team_id, user_id, role, timestamps.
- `inboxes` — id, team_id, channel_type, name, config (json, encrypted-at-rest),
  is_active, timestamps.
- `contacts` — id, team_id, display_name, timestamps.
- `contact_channel_identities` — id, contact_id, channel_type, external_id
  (e.g. email address / Slack user id / phone number / IG handle), timestamps.
- `conversations` — id, team_id, inbox_id, contact_id, subject, status, priority,
  assignee_id (nullable, users.id), sla_due_at, first_response_at, resolved_at,
  timestamps.
- `messages` — id, conversation_id, direction, channel, sender_type, sender_id,
  body, raw_payload (json), ai_generated (bool), status (sent/draft), timestamps.
- `notes` — id, conversation_id, user_id, body, mentioned_user_ids (json),
  timestamps.
- `labels` — id, team_id, name, color, timestamps.
- `conversation_label` — conversation_id, label_id.
- `sla_policies` — id, team_id, priority, first_response_minutes,
  resolution_minutes, timestamps.
- `mcp_connections` — id, team_id, composio_connection_id, app_slug, scopes (json),
  status, timestamps.
- `integration_settings` — id, team_id, integration_key, enabled, config (json,
  encrypted-at-rest), timestamps.

All team-scoped tables get a foreign key to `teams` and are covered by the
`BelongsToTeam` global scope.

---

## 6. UI requirements (Inertia + React)

Key screens/components:

- **Inbox list view** — conversation list filterable by status/label/assignee/channel,
  with unread indicators, live-updating via the broadcast hook.
- **Conversation thread view** — message timeline (customer + agent messages,
  visually distinct), an internal **Notes** tab/panel separate from the customer
  thread, @mention autocomplete in notes.
- **Compose/reply box** — rich text or markdown, an "AI draft" button that calls the
  draft endpoint and populates the box (editable before sending), attachment support
  stub (document as extension point if not building file uploads in v1).
- **Channel connection settings** — per channel type, a connect/configure flow;
  for MCP tools, a "Connect via Composio" button per available app with connection
  status and a disconnect action.
- **Label & SLA management** — CRUD for labels and SLA policies (team admin only).
- **Team/workspace switcher** — in the app header, switches `CurrentTeam` context.
- Use Tailwind CSS utility classes; keep components composable so a host app can
  override/extend specific pages by publishing them (support Laravel's typical
  "publish views/components to customize" pattern where practical for React —
  document the approach, e.g. via a resolvable component map).

---

## 7. API surface

- `POST /shared-inbox/webhooks/{channel}` — inbound message webhook per channel,
  signature-verified per provider, queued for processing (dispatch a job that calls
  `ChannelDriver::normalizeInbound` then creates/updates `Conversation`/`Message`
  and fires `MessageReceived`).
- `POST /shared-inbox/conversations/{conversation}/messages` — send an outbound
  message (agent reply) through the conversation's channel driver.
- `POST /shared-inbox/conversations/{conversation}/ai-draft` — generate an AI draft
  reply via the configured `AiReplyDriver`.
- CRUD endpoints for `conversations`, `notes`, `labels`, `sla-policies`,
  `inboxes` (settings), `mcp-connections` (connect/list/disconnect), and
  `integration-settings`.
- `GET/POST /shared-inbox/mcp/callback` — Composio OAuth callback handler.

All routes scoped/authorized against the current team and the acting user's role.

---

## 8. Security & config

- Encrypt all stored credentials/tokens at rest (`config`/`credentials` JSON columns
  on `inboxes`, `integration_settings`; Composio connection metadata) using Laravel's
  `encrypted` cast.
- Verify webhook signatures per provider convention before processing any inbound
  payload (reject unsigned/invalid requests with 401/403, no DB writes).
- Composio OAuth callback must validate `state` to prevent CSRF; connections
  scoped strictly to the initiating team.
- Rate-limit all webhook routes (per-channel throttle) to blunt abuse/replay.
- No credentials ever logged; scrub `raw_payload` of secrets before persisting if a
  provider embeds signing secrets in payloads.

---

## 9. Testing

Use Pest (preferred, matches modern Laravel package conventions) or PHPUnit:

- **Channel driver tests**: for each driver, feed a realistic sample provider
  payload (fixture file) through `normalizeInbound` and assert the resulting
  `Conversation`/`Message` state; test `send()` against a faked HTTP client
  (Laravel `Http::fake()`).
- **AI driver contract tests**: a `FakeAiReplyDriver` used in app-level tests;
  separate integration-style tests (skippable/mocked) for the OpenAI/Anthropic
  adapters using `Http::fake()`.
- **MCP/Composio tests**: mock Composio API responses; test the OAuth callback
  handler's state validation and connection persistence.
- **Multi-tenancy tests**: assert cross-team data leakage is impossible (a user on
  Team A cannot query/act on Team B's conversations/inboxes).
- **Inertia response tests**: assert key pages render with expected props
  (`assertInertia`).
- **Broadcast event tests**: assert events fire with correct channel names/payloads
  (`Event::fake()`), without requiring a real broadcaster.

---

## 10. Phased build order

Work through these phases in order; each should be independently testable/mergeable:

1. Package skeleton: `composer.json`, service provider, config file, install command,
   CI workflow stub (lint + test).
2. Core multi-tenant data model: `teams`, `team_user`, `CurrentTeam`, migrations,
   base Eloquent models + `BelongsToTeam` scope, factories.
3. Email channel end-to-end: `Inbox`, `Conversation`, `Message`, `Contact` fully
   working for email only — inbound webhook → normalize → store → outbound send.
4. Inertia/React inbox UI for the email-only MVP (list view, thread view, compose,
   send) so there's a working vertical slice before adding channels.
5. Slack, WhatsApp, Instagram channel drivers (each following the email driver's
   pattern), plus Slack routing notifications for new tickets.
6. AI draft abstraction: contract + `NullAiDriver` + OpenAI + Anthropic reference
   drivers + the "AI draft" UI button/endpoint.
7. Composio MCP integration: connection settings UI, OAuth callback, `McpToolProvider`,
   at least one working tool-call example wired into AI drafting.
8. Optional integrations: Linear, HubSpot, Betterstack, each behind config flags with
   their own settings UI panel.
9. Broadcasting events + React `useSharedInboxChannel` hook + documentation for host
   apps to wire up Reverb/Pusher/Ably; polling fallback when unconfigured.
10. Full test suite pass, README with installation/configuration instructions for
    every channel and integration, and a `php artisan shared-inbox:install` command
    that publishes config/migrations and prints next steps.

---

## 11. Open questions to confirm before/while building

- Final Composer package name and root PHP namespace (placeholder used above:
  `vendor/laravel-shared-inbox`, `Vendor\SharedInbox`).
- Which inbound email payload format to support first (Postmark vs SES vs Mailgun) —
  pick one for the MVP driver and document the contract for adding others.
- Whether per-team AI provider selection is needed in v1 or global config is
  sufficient for the first release.
- File/attachment handling for messages — in scope for v1 or a documented
  follow-up?
- Whether `User` should be a model the package defines/migrates, or purely a
  contract/trait applied to the host app's existing `User` model (recommended:
  the latter, to avoid conflicting with the host app's auth setup — package should
  work with the host app's existing `users` table via a trait/interface, not own it).
