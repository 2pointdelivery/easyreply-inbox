<?php

// config/shared-inbox.php
//
// Published via `php artisan shared-inbox:install` (or `vendor:publish --tag=shared-inbox-config`).
// Sections beyond `channels`/`ai`/`mcp`/`integrations` keys are placeholders wired up
// in later build phases (see BUILD_PROMPT.md) — the structure is fixed now so later
// phases have a stable config surface to implement against.

return [

    /*
    |--------------------------------------------------------------------------
    | Current Team Resolver
    |--------------------------------------------------------------------------
    |
    | Class implementing Easyreply\Inbox\Support\Contracts\CurrentTeam, used to
    | resolve the active team for the current request. The package ships a
    | sensible default (first team the authenticated user belongs to); override
    | this if the host app has its own tenancy/session concept of "current team".
    |
    */
    'current_team_resolver' => \Easyreply\Inbox\Support\CurrentTeam::class,

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | Each channel driver is independently enabled. `driver` maps to a class
    | registered on the ChannelManager (see src/Channels/ChannelManager.php).
    | Channel-specific credentials live per-Inbox in the database, not here —
    | these are just which drivers are available and their default config.
    |
    */
    'channels' => [

        'email' => [
            'enabled' => true,
            'driver' => \Easyreply\Inbox\Channels\EmailChannelDriver::class,
            // Which inbound webhook payload format to parse: 'postmark' or
            // 'mailgun'. See EmailChannelDriver::normalizeInbound() — add a
            // parser + a new value here to support another provider's shape.
            'inbound_format' => env('SHARED_INBOX_EMAIL_INBOUND_FORMAT', 'postmark'),
            // Shared secret the inbound webhook request must present (e.g. as a
            // query string or custom header, configured on the provider side)
            // before any payload is processed.
            'webhook_secret' => env('SHARED_INBOX_EMAIL_WEBHOOK_SECRET'),
        ],

        'slack' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Channels\SlackChannelDriver::class,
            // Signing secret for the Slack App backing this channel (Slack
            // app-level, shared across every Slack-connected inbox).
            'signing_secret' => env('SLACK_SIGNING_SECRET'),
        ],

        'whatsapp' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Channels\WhatsAppChannelDriver::class,
            // Meta app secret + webhook verify token (Meta app-level, shared
            // across every WhatsApp-connected inbox). Per-inbox credentials
            // (access_token, phone_number_id) live on the Inbox record.
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        ],

        'instagram' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Channels\InstagramChannelDriver::class,
            // Meta app secret + webhook verify token (Meta app-level, shared
            // across every Instagram-connected inbox). Per-inbox credentials
            // (access_token, page_id) live on the Inbox record.
            'app_secret' => env('INSTAGRAM_APP_SECRET'),
            'verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Routing Notifications
    |--------------------------------------------------------------------------
    |
    | Separate from Slack as an inbound channel: posts a notification to a
    | Slack channel whenever a new conversation comes in on ANY channel, so
    | the right people see it without opening the inbox. See
    | BUILD_PROMPT.md §3.3 ("route new tickets/replies to a Slack channel").
    |
    */
    'slack_routing' => [
        'enabled' => env('SHARED_INBOX_SLACK_ROUTING_ENABLED', false),
        'bot_token' => env('SLACK_ROUTING_BOT_TOKEN'),
        'channel' => env('SLACK_ROUTING_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Drafting
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic: `driver` resolves via the AiDriverManager. Ships a
    | NullAiDriver by default (no-op) so the package works with zero AI config.
    |
    */
    'ai' => [
        'driver' => env('SHARED_INBOX_AI_DRIVER', 'null'),

        'drivers' => [
            'null' => \Easyreply\Inbox\Ai\NullAiDriver::class,
            'openai' => \Easyreply\Inbox\Ai\OpenAiReplyDriver::class,
            'anthropic' => \Easyreply\Inbox\Ai\AnthropicReplyDriver::class,
        ],

        // Reference driver credentials. Only the one selected above via
        // `driver` is ever called; both are equally-supported examples, not
        // a recommendation of one over the other.
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-latest'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Message attachments are stored on a standard Laravel filesystem disk —
    | set to any disk already configured in the host app's config/filesystems.php.
    | Attachments are stored and shown in the UI only; they are not yet
    | forwarded through ChannelDriver::send() to the provider (documented
    | follow-up — see README "Known limitations").
    |
    */
    'attachments' => [
        'disk' => env('SHARED_INBOX_ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => env('SHARED_INBOX_ATTACHMENTS_MAX_KB', 10240),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (Composio)
    |--------------------------------------------------------------------------
    |
    | Composio.dev is the OAuth/connection broker for MCP tool access. The
    | package never stores raw provider tokens itself — only Composio's
    | connection reference. See BUILD_PROMPT.md §3.5.
    |
    */
    'mcp' => [
        'enabled' => env('SHARED_INBOX_MCP_ENABLED', false),
        'composio_api_key' => env('COMPOSIO_API_KEY'),
        'base_url' => env('COMPOSIO_BASE_URL', 'https://backend.composio.dev/api/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice Reception Agents
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic AI receptionists (ElevenLabs, OpenAI Realtime,
    | Aircall, Vapi/Retell/Bland via generic-sip). `driver` resolves via the
    | VoiceAgentManager. Ships a NullVoiceDriver by default (log-only, never
    | dials) so the package works with zero voice config.
    |
    | Strict no-audio policy: transcript + summary + metadata only. The
    | package never fetches, persists, or returns audio bytes or recording
    | URLs — providers may host audio on their side, but no link is stored.
    |
    */
    'voice' => [
        'driver' => env('SHARED_INBOX_VOICE_DRIVER', 'null'),

        'drivers' => [
            'null' => \Easyreply\Inbox\Voice\NullVoiceDriver::class,
            'elevenlabs' => \Easyreply\Inbox\Voice\ElevenLabsVoiceDriver::class,
            'openai-realtime' => \Easyreply\Inbox\Voice\OpenAiRealtimeVoiceDriver::class,
            'aircall' => \Easyreply\Inbox\Voice\AircallVoiceDriver::class,
            'generic-sip' => \Easyreply\Inbox\Voice\GenericSipVoiceDriver::class,
        ],

        // Single shared number for v1 (E.164). Teams route via IVR selection.
        'shared_number_e164' => env('SHARED_INBOX_VOICE_NUMBER'),

        'routing' => [
            'mode' => env('SHARED_INBOX_VOICE_ROUTING', 'ivr-team-select'),
            'prompt' => 'Thank you for calling. Please say which department you need.',
            'business_hours' => null,
        ],

        'recording' => [
            'store_audio' => false,
            'store_recording_url' => false,
        ],

        'retention_days' => env('SHARED_INBOX_VOICE_RETENTION_DAYS', 90),

        'transfer' => [
            'enabled' => env('SHARED_INBOX_VOICE_TRANSFER_ENABLED', true),
            'default_target_e164' => env('SHARED_INBOX_VOICE_TRANSFER_TARGET'),
        ],

        'outbound' => [
            'click_to_call' => env('SHARED_INBOX_VOICE_CLICK_TO_CALL', true),
            'event_callbacks' => env('SHARED_INBOX_VOICE_EVENT_CALLBACKS', false),
            'bulk' => env('SHARED_INBOX_VOICE_BULK', false),
            'rate_per_min' => env('SHARED_INBOX_VOICE_RATE_PER_MIN', 10),
        ],

        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'agent_id' => env('ELEVENLABS_AGENT_ID'),
            'webhook_secret' => env('ELEVENLABS_WEBHOOK_SECRET'),
        ],

        'openai' => [
            'realtime_api_key' => env('OPENAI_API_KEY'),
            'webhook_secret' => env('OPENAI_VOICE_WEBHOOK_SECRET'),
        ],

        'aircall' => [
            'api_key' => env('AIRCALL_API_KEY'),
            'webhook_secret' => env('AIRCALL_WEBHOOK_SECRET'),
        ],

        'generic_sip' => [
            'webhook_secret' => env('VOICE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Integrations
    |--------------------------------------------------------------------------
    |
    | Each integration is independently enabled and never required for core
    | inbox functionality. See BUILD_PROMPT.md §3.7.
    |
    */
    'integrations' => [

        'linear' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Integrations\LinearIntegration::class,
        ],

        'hubspot' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Integrations\HubSpotIntegration::class,
        ],

        'betterstack' => [
            'enabled' => false,
            'driver' => \Easyreply\Inbox\Integrations\BetterstackIntegration::class,
        ],

    ],

];
