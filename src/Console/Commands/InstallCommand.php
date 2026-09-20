<?php

namespace Easyreply\Inbox\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    public $signature = 'shared-inbox:install';

    public $description = 'Publish the shared inbox package config and print setup next steps.';

    public function handle(): int
    {
        $this->comment('Installing Shared Inbox...');

        $this->call('vendor:publish', [
            '--tag' => 'shared-inbox-config',
            '--force' => false,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'shared-inbox-migrations',
            '--force' => false,
        ]);

        $this->info('Shared Inbox config and migrations published.');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Make sure your User model uses the Easyreply\Inbox\Concerns\BelongsToTeams trait.');
        $this->line('  2. Review the published migrations, then run: php artisan migrate');
        $this->line('  3. Review config/shared-inbox.php and enable the channels you need.');
        $this->line('  4. Create an Inbox row per connected channel (email address, Slack app, WhatsApp/');
        $this->line('     Instagram number) — see README.md for each channel\'s webhook URL and setup.');
        $this->line('  5. Set SHARED_INBOX_AI_DRIVER and a provider key in .env if using AI drafting.');
        $this->line('  6. Set COMPOSIO_API_KEY in .env if using MCP tool access.');
        $this->line('  7. Set SHARED_INBOX_VOICE_DRIVER + SHARED_INBOX_VOICE_NUMBER in .env if using');
        $this->line('     AI reception agents; visit /shared-inbox/settings/voice for per-team overrides.');
        $this->line('  8. Visit /shared-inbox/settings/widget to enable the floating website chat/call');
        $this->line('     bubble, and schedule `shared-inbox:dispatch-voice-schedules` for callbacks.');
        $this->line('  9. Wire up your Vite/Inertia build to resolve this package\'s pages — see README.md');
        $this->line('     "Frontend integration".');
        $this->line('  10. Configure a broadcaster (Reverb/Pusher/Ably) and Laravel Echo if you want live');
        $this->line('     updates — see README.md "Real-time". The UI works via polling without it.');
        $this->newLine();
        $this->line('Full setup details: README.md');

        return self::SUCCESS;
    }
}
