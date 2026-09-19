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

Supports two inbound webhook payload shapes — [Postmark](https://postmarkapp.com)
(default) and [Mailgun](https://www.mailgun.com)'s "Store and Notify" inbound
webhook — selected via `SHARED_INBOX_EMAIL_INBOUND_FORMAT` (`postmark` or
`mailgun`). See `EmailChannelDriver::normalizeInbound()` to add another
provider's payload shape (e.g. SES).

1. On your provider, set the inbound webhook URL to
   `https://your-app.test/shared-inbox/webhooks/email/{inbox}`.
2. Set `SHARED_INBOX_EMAIL_WEBHOOK_SECRET` in `.env` and configure the
   provider to send it as an `X-Shared-Inbox-Webhook-Secret` header (or a
   custom header rule) — without it, the endpoint accepts unsigned requests.
   This is a package-level shared secret, not either provider's own native
   request-signing scheme.
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
`/shared-inbox/settings/integrations` — which also has a form for entering
that integration's credentials (write-only: once saved, the UI only shows
"Credentials configured", never the stored value back).

| Integration | Config flag | Team credentials (`IntegrationSetting.config`) | What it does |
|---|---|---|---|
| Linear | `integrations.linear.enabled` | `api_key`, `team_id` (Linear team) | Link a conversation to a new/existing Linear issue |
| HubSpot | `integrations.hubspot.enabled` | `api_key` (private app token) | Show CRM context (company, lifecycle stage) in the conversation sidebar |
| Betterstack | `integrations.betterstack.enabled` | `api_key` | Show an active-incident banner in the conversation sidebar |

## Labels, SLA policies, and internal notes

Three team-scoped organization features, all opt-in and none required for
the base inbox to work:

- **Labels** — `/shared-inbox/settings/labels` (team owners/admins only)
  manages the team's label set (name + color); an agent attaches/detaches
  them on a conversation from its show page (`LabelPicker`). Routes:
  `POST`/`DELETE /shared-inbox/conversations/{conversation}/labels/{label}`.
- **SLA policies** — `/shared-inbox/settings/sla-policies` (owners/admins
  only) sets `first_response_minutes`/`resolution_minutes` per priority.
  A new conversation's `sla_due_at` is computed automatically from the
  team's policy matching its priority (see `Conversation::booted()` and
  `Support/SlaCalculator`); changing a conversation's priority recomputes
  it. `first_response_at` is stamped the first time an agent sends an
  outbound message; `resolved_at` when it's marked closed.
- **Internal notes** — a conversation's "Notes" tab (separate from the
  customer-facing thread) for team-only commentary. Typing `@` offers a
  filtered list of team members; mentioned user ids are stored on the note
  (`mentioned_user_ids`) but no notification is dispatched yet — wire that
  up in a `NoteCreated`-style listener if you need it, filtering
  `Note::mentioned_user_ids` isn't itself a broadcastable event today.

Team roles come from the existing `team_user.role` column — `owner` or
`admin` can manage labels/SLA policies, any team member manages notes and
attaches existing labels.

## Attachments

Agents can attach files to an outbound reply from the compose box. Stored
on a standard Laravel filesystem disk (`config('shared-inbox.attachments.disk')`,
default `local`; `SHARED_INBOX_ATTACHMENTS_MAX_KB` caps upload size, default
10MB) and shown as download links in the message thread.

Attachments are **not yet forwarded to the channel provider** — `ChannelDriver::send()`
only receives the reply body today. Forwarding an attachment through, say,
Slack's `files.upload` or an email's MIME parts is provider-specific enough
that it's left as a documented follow-up per driver rather than bolted on
generically here.

## Per-team AI provider override

`SHARED_INBOX_AI_DRIVER` in `.env` sets the package-wide default driver, but
an individual team can override it at `/shared-inbox/settings/ai` — pick any
driver registered in `config('shared-inbox.ai.drivers')`, or "Use global
default" to clear the override. `AiDraftController` reads the acting team's
`ai_driver` column first, falling through to the global config only when
it's unset.

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

The test suite (93 tests) runs entirely against an in-memory sqlite database
via Orchestra Testbench — no external services are contacted; every outbound
HTTP call (Slack, Meta, OpenAI, Anthropic, Composio, Linear, HubSpot,
Betterstack) is faked with `Http::fake()`/`Mail::fake()`/`Event::fake()` in
the relevant tests. `composer test` is exactly what CI runs
(`.github/workflows/tests.yml`), across PHP 8.2/8.3 × Laravel 10/11.

## Known limitations / beyond v1

Deliberately out of scope for this package as built — noted here rather than
left implicit:

- **Attachments aren't forwarded to channel providers** — they're stored and
  shown in the UI, but `ChannelDriver::send()` still only sends the reply
  body. Per-provider attachment delivery (Slack file upload, email MIME
  parts, ...) is a documented follow-up.
- **Mentioning a teammate in a note doesn't send a notification** — the
  mentioned user ids are recorded (`Note::mentioned_user_ids`), but nothing
  dispatches a Laravel notification for them yet.
- **SES inbound email isn't implemented** — Postmark and Mailgun are; add a
  third `normalize*Inbound()` branch to `EmailChannelDriver` for SES's shape.
- **Per-team AI provider selection is driver-only** — a team can choose which
  configured driver to use, not a per-team API key/model on top of that
  (those still come from the package-wide `config('shared-inbox.ai')`).

## AI reception agents (voice)

Provider-agnostic voice receptionists with call log, transcript + summary,
sentiment triage, and post-call actions. Drivers: `null` (log-only default),
`elevenlabs`, `openai-realtime`, `aircall`, `generic-sip` (covers
Vapi/Retell/Bland via payload normalizers) — resolved via `VoiceAgentManager`
from `config('shared-inbox.voice.driver')`, with per-team override
(`teams.voice_driver`, `/shared-inbox/settings/voice`).

```
SHARED_INBOX_VOICE_DRIVER=elevenlabs
SHARED_INBOX_VOICE_NUMBER=+15550000000
ELEVENLABS_API_KEY=...
ELEVENLABS_AGENT_ID=...
```

- **Single shared number (v1):** teams route via IVR team-select (`team_id`
  on the webhook) or the agent default. Each call creates/links a `CallLog`
  + `Contact` (voice E.164 identity) + `Conversation` on a `voice` inbox +
  thread message, then runs triage (priority suggestion → SLA), HubSpot
  context lookup, Slack routing ping, and audits each step to `call_actions`.
- **Strict no-audio policy:** transcript + summary + metadata only. No audio
  bytes fetched, no recording URLs persisted (no such columns exist), PII
  digit-runs redacted, provider secrets scrubbed from `raw_payload`.
  Retention: `php artisan shared-inbox:purge-voice-transcripts` wipes
  transcripts/summaries older than `voice.retention_days` (default 90),
  keeping metadata + audit rows.
- **Outbound:** click-to-call from a conversation, event auto-callbacks
  (opt-in), scheduled/bulk via `voice_schedules` (admin-only, rate-limited).
  Live transfer via driver `transferCall()` with outcome logged; on failure
  the call falls back to message + Slack notify.
- **Webhooks** (`routes/api.php`, throttled, signature-verified, 401 on
  failure, noise ignored): `POST webhooks/voice/{provider}`,
  `POST voice/{provider}/status-callback`,
  `POST voice/{provider}/transfer-callback`.
- **UI:** `/shared-inbox/voice/calls` (log list),
  `/shared-inbox/voice/calls/{id}` (transcript + actions + retry),
  `/shared-inbox/settings/voice` (global default + team override).
  Broadcasts `CallLogged`/`CallUpdated` on the existing team/conversation
  channels with polling fallback.
- **Callback dispatch:** `php artisan shared-inbox:dispatch-voice-schedules`
  dials due callbacks (widget requests + scheduled follow-ups) within the
  per-minute rate limit — schedule it (cron/scheduler) for callbacks to go
  out.

## Website widget (floating chat + call requests)

One script tag adds a floating chat/call bubble to any site — no account
needed for visitors:

```html
<script src="https://your-app.test/shared-inbox/widget.js"
        data-inbox="123" data-token="..." defer></script>
```

Get the snippet at `/shared-inbox/settings/widget` (owners/admins only):
create a Website inbox, enable it, copy the snippet. Regenerate rotates the
token (existing embeds stop working until updated).

- **Live chat:** visitor starts a session (name/email optional) → new
  `Contact` + `Conversation` on the widget inbox, visible instantly in the
  team inbox (`MessageReceived` still fires). Agent replies send through the
  standard reply box; the guest picks them up via polling. No login, no
  session — each visitor holds a random session token and every endpoint is
  throttled (`start` 10/min, `call-request` 5/min).
- **Calls:** the Call tab shows your dial-in number
  (`SHARED_INBOX_VOICE_NUMBER`) plus a "Call me back" form (E.164 number +
  optional topic). Requests queue as `VoiceSchedule` rows the AI agent dials
  via the dispatch command above; transcripts stay text-only per the
  no-audio policy. Disable with `SHARED_INBOX_VOICE_WIDGET_CALLBACKS=false`.
- **Internals:** `WidgetChannelDriver` (agent replies are stored + polled,
  delivery is a no-op), public endpoints under `shared-inbox/widget/*`
  (`routes/api.php`), iframe page + loader served by `WidgetController`
  (all widget CSS/JS lives in the iframe, so host-page styles never clash).

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
