# Shared Inbox for Laravel

A self-hosted, installable Laravel package that unifies email, Slack, WhatsApp, and
Instagram into one team inbox — with AI-drafted replies and MCP tool access via
Composio.dev. Inspired by [EasyReply](https://easyreply.io).

> **Status: build phase 10 of 10 — feature-complete against the build prompt.** See
> [BUILD_PROMPT.md](BUILD_PROMPT.md) for the full spec and phased build order this
> package is being implemented against.

## Installation

```bash
composer require easyreply/inbox
php artisan shared-inbox:install
```

This publishes `config/shared-inbox.php`. Review it and enable the channels,
AI driver, and integrations you need — every feature beyond the base install is
opt-in via config. Every channel/inbox connection also needs an `Inbox` row —
create one per connected channel (e.g. via `php artisan tinker` or your own
onboarding UI):

```php
use Easyreply\Inbox\Models\Inbox;

Inbox::create([
    'team_id' => $team->id,
    'channel_type' => 'email',
    'name' => 'Support',
    'config' => ['address' => 'support@example.com'],
]);
```

## Channels

Every channel is disabled by default except email. Enable one by flipping its
`enabled` flag in `config/shared-inbox.php` (or the matching env var) and
setting up the provider side as below. Each channel's webhook URL embeds the
`Inbox` id: `/shared-inbox/webhooks/{channel}/{inbox}`.

### Email

Uses [Postmark](https://postmarkapp.com)'s inbound webhook format (the only
inbound format implemented — see `EmailChannelDriver` to add another
provider's payload shape).

1. On Postmark, set the inbound webhook URL for your server to
   `https://your-app.test/shared-inbox/webhooks/email/{inbox}`.
2. Set `SHARED_INBOX_EMAIL_WEBHOOK_SECRET` in `.env` and configure Postmark to
   send it as an `X-Shared-Inbox-Webhook-Secret` header (or a custom header
   rule) — without it, the endpoint accepts unsigned requests.
3. Outbound replies send via Laravel's own configured mailer
   (`config/mail.php`), `From` set to the `Inbox`'s `config.address`.

### Slack

1. Create a Slack App, subscribe it to the `message.channels` /
   `message.im` Events API events, and point the Request URL at
   `https://your-app.test/shared-inbox/webhooks/slack/{inbox}`. Slack's
   `url_verification` handshake is handled automatically.
2. Set `SLACK_SIGNING_SECRET` in `.env` (from the Slack App's Basic
   Information page) — request signatures are rejected without it.
3. Set the `Inbox.config.bot_token` to the App's Bot User OAuth Token
   (`xoxb-...`), used both to send replies and — separately — for the "route
   new tickets to a Slack channel" notifications (see below).

### WhatsApp / Instagram

Both use the same Meta Graph API webhook scheme (a GET handshake to verify
the endpoint, then POST deliveries) via `MetaWebhookController`.

1. In the Meta App dashboard, configure the webhook URL as
   `https://your-app.test/shared-inbox/webhooks/whatsapp/{inbox}` (or
   `instagram/{inbox}`), and set a verify token matching
   `WHATSAPP_WEBHOOK_VERIFY_TOKEN` / `INSTAGRAM_WEBHOOK_VERIFY_TOKEN`.
2. Set `WHATSAPP_APP_SECRET` / `INSTAGRAM_APP_SECRET` — inbound payload
   signatures (`X-Hub-Signature-256`) are rejected without the matching one.
3. Set `Inbox.config.access_token` + `Inbox.config.phone_number_id` (WhatsApp)
   or `Inbox.config.access_token` + `Inbox.config.page_id` (Instagram).

### Slack routing notifications

Separate from Slack as a channel: posts to a Slack channel whenever a **new**
conversation comes in on *any* channel, so the right people see it without
opening the inbox.

```
SHARED_INBOX_SLACK_ROUTING_ENABLED=true
SLACK_ROUTING_BOT_TOKEN=xoxb-...
SLACK_ROUTING_CHANNEL=#support
```

## AI drafting

Off by default (`SHARED_INBOX_AI_DRIVER=null`). Two equally-supported
reference drivers ship — pick one, or implement `AiReplyDriver` yourself for
any other provider:

```
SHARED_INBOX_AI_DRIVER=openai      # or: anthropic
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini           # optional, this is the default
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-5-haiku-latest
```

Clicking "AI draft" in a conversation calls the configured driver and stores
the suggestion as a real (editable) draft message — nothing is sent until an
agent reviews and hits send.

## MCP tool access (Composio)

Lets AI drafts and agents securely reach external tools (Gmail, Linear, ...)
through OAuth connections a team authorizes, brokered by
[Composio](https://composio.dev) — the package never stores a raw provider
token itself, only Composio's connection reference.

```
SHARED_INBOX_MCP_ENABLED=true
COMPOSIO_API_KEY=...
```

Then visit `/shared-inbox/settings/mcp` to connect an app per team. See
`McpToolProvider::executeTool()` for calling a connected tool from your own
`AiReplyDriver` implementation (optional — not required for AI drafting to
work).

## Optional integrations

Never required for the inbox to function. Each needs two things on: the
package-wide flag in `config/shared-inbox.php`, and the team's own toggle at
`/shared-inbox/settings/integrations`.

| Integration | Config flag | Team credentials (`IntegrationSetting.config`) | What it does |
|---|---|---|---|
| Linear | `integrations.linear.enabled` | `api_key`, `team_id` (Linear team) | Link a conversation to a new/existing Linear issue |
| HubSpot | `integrations.hubspot.enabled` | `api_key` (private app token) | Show CRM context (company, lifecycle stage) in the conversation sidebar |
| Betterstack | `integrations.betterstack.enabled` | `api_key` | Show an active-incident banner in the conversation sidebar |

## Frontend integration

The inbox UI ships as Inertia + React source (`resources/js/Pages`,
`resources/js/Components`) meant to be picked up by the **host app's own** Vite +
Inertia + React build — the package does not bundle or compile its own assets.
The host app needs:

1. `@inertiajs/react`, `react`, and `react-dom` installed (the host is assumed to
   already run this stack — see BUILD_PROMPT.md).
2. An alias in `vite.config.js` so the host's `import.meta.glob` page resolver can
   find this package's pages, e.g.:

   ```js
   resolve: {
     alias: {
       '@shared-inbox': '/vendor/easyreply/inbox/resources/js',
     },
   },
   ```

3. The host's Inertia `resolve()` (in `resources/js/app.jsx`) to glob both its own
   pages and the package's:

   ```js
   resolve: (name) => {
     const pages = import.meta.glob(
       ['./Pages/**/*.jsx', '/vendor/easyreply/inbox/resources/js/Pages/**/*.jsx'],
       { eager: true },
     )
     return pages[`./Pages/${name}.jsx`] ?? pages[`/vendor/easyreply/inbox/resources/js/Pages/${name}.jsx`]
   },
   ```

Visiting `/shared-inbox` then renders the `Inbox/Index` page (conversation list,
filterable by status) and `/shared-inbox/conversations/{id}` renders `Inbox/Show`
(message thread + reply compose box, posting to the outbound-message endpoint).

## Real-time

The package broadcasts four events — `MessageReceived`, `MessageSent`,
`ConversationUpdated`, `ConversationAssigned` — on two private channels per
team/conversation:

- `shared-inbox.team.{teamId}` — subscribed by the inbox list page
- `shared-inbox.conversation.{conversationId}` — subscribed by an open
  conversation's page

It ships **no broadcaster configuration** — that's entirely the host app's
choice (Reverb, Pusher, Ably, ...). To get live updates:

1. Configure a broadcaster in the host app as normal (`BROADCAST_CONNECTION`
   in `.env`, `config/broadcasting.php`).
2. Install and configure [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation)
   in the host app's own `resources/js/app.jsx`, so `window.Echo` exists.
3. Call `Broadcast::routes()` (or the equivalent for your broadcaster) in the
   host app so clients can authenticate against private channels — the
   package registers the channel authorization callbacks themselves
   (`shared-inbox.team.{teamId}` and `shared-inbox.conversation.{conversationId}`,
   both requiring the authenticated user to belong to that team via
   `BelongsToTeams`), it just doesn't expose the `/broadcasting/auth` route.

Without `window.Echo` configured, `useSharedInboxChannel` (see
`resources/js/hooks/useSharedInboxChannel.js`) falls back to polling every 15
seconds, so the UI still reflects new activity — just not instantly.

## Development

```bash
composer install
composer test
```

The test suite (79 tests) runs entirely against an in-memory sqlite database
via Orchestra Testbench — no external services are contacted; every outbound
HTTP call (Slack, Meta, OpenAI, Anthropic, Composio, Linear, HubSpot,
Betterstack) is faked with `Http::fake()`/`Mail::fake()`/`Event::fake()` in
the relevant tests. `composer test` is exactly what CI runs
(`.github/workflows/tests.yml`), across PHP 8.2/8.3 × Laravel 10/11.

## Known limitations / beyond v1

Deliberately out of scope for this package as built — noted here rather than
left implicit:

- **Labels, SLA policies, and internal notes** are in the original data-model
  spec (see BUILD_PROMPT.md §5) but weren't part of the 10-phase build order
  actually executed; `Conversation` has `status`/`priority`/`assignee_id`
  columns ready for them, but no `labels`, `sla_policies`, or `notes` tables
  exist yet.
- **Attachments** on messages aren't handled.
- **Per-team AI provider selection** isn't implemented — `SHARED_INBOX_AI_DRIVER`
  is one global choice, not configurable per team.
- **A settings UI for entering integration API keys** doesn't exist — the
  enable/disable toggle at `/shared-inbox/settings/integrations` works, but
  credentials must be set directly on the `IntegrationSetting.config` column
  today.
- **Which email inbound format to support** was resolved as Postmark only
  (see BUILD_PROMPT.md §11) — add a parser to `EmailChannelDriver` for SES,
  Mailgun, etc.

## Roadmap

See [BUILD_PROMPT.md](BUILD_PROMPT.md) §10 for the full phased build order:

- [x] Phase 1 — Package skeleton (config, service provider, install command, CI)
- [x] Phase 2 — Core multi-tenant data model
- [x] Phase 3 — Email channel end-to-end
- [x] Phase 4 — Inertia/React inbox UI (email MVP)
- [x] Phase 5 — Slack, WhatsApp, Instagram channels
- [x] Phase 6 — AI draft replies (provider-agnostic)
- [x] Phase 7 — Composio MCP tool access
- [x] Phase 8 — Optional integrations (Linear, HubSpot, Betterstack)
- [x] Phase 9 — Real-time broadcasting
- [x] Phase 10 — Full test suite + docs

## License

MIT. See [LICENSE.md](LICENSE.md).
