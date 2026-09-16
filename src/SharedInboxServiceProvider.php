<?php

namespace Easyreply\Inbox;

use Easyreply\Inbox\Ai\AiDriverManager;
use Easyreply\Inbox\Broadcasting\AuthorizeConversationChannel;
use Easyreply\Inbox\Broadcasting\AuthorizeTeamChannel;
use Easyreply\Inbox\Channels\ChannelManager;
use Easyreply\Inbox\Console\Commands\InstallCommand;
use Easyreply\Inbox\Integrations\IntegrationManager;
use Easyreply\Inbox\Mcp\ComposioClient;
use Easyreply\Inbox\Mcp\McpToolProvider;
use Easyreply\Inbox\Support\Contracts\CurrentTeam as CurrentTeamContract;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Inertia;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SharedInboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // This class is a Package Service Provider (spatie/laravel-package-tools).
        $package
            ->name('shared-inbox')
            ->hasConfigFile('shared-inbox')
            ->hasViews()
            ->hasRoute('web')
            ->hasRoute('api')
            ->hasCommand(InstallCommand::class)
            ->discoversMigrations();

        // Deliberately does NOT call ->runsMigrations(): migrations are
        // published (see InstallCommand) for the host app to review and run
        // itself via `php artisan migrate`, rather than silently auto-run —
        // the standard, safer convention for distributable Laravel packages.
        // Tests load the package's migrations directly (see tests/TestCase).
    }

    public function packageBooted(): void
    {
        // The host app's own Inertia setup takes precedence if it already
        // called Inertia::setRootView() — this just gives the package a
        // sensible default root view (resources/views/app.blade.php,
        // published under the "shared-inbox" view namespace).
        Inertia::setRootView('shared-inbox::app');

        // Registering channel authorization callbacks doesn't require an
        // active broadcaster driver, only that broadcasting itself is
        // bound — which isn't guaranteed for every consuming app (or a
        // bare test harness), so this is guarded rather than assumed.
        if ($this->app->bound(BroadcastingFactory::class)) {
            $this->registerBroadcastChannels();
        }
    }

    /**
     * Private channels every event in src/Events broadcasts on. The
     * package never assumes what the host app's User model looks like
     * beyond the BelongsToTeams trait (see Concerns/BelongsToTeams.php).
     */
    protected function registerBroadcastChannels(): void
    {
        Broadcast::channel('shared-inbox.team.{teamId}', AuthorizeTeamChannel::class);
        Broadcast::channel('shared-inbox.conversation.{conversationId}', AuthorizeConversationChannel::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(
            CurrentTeamContract::class,
            fn ($app) => $app->make($app['config']->get('shared-inbox.current_team_resolver'))
        );

        $this->app->singleton(ChannelManager::class);
        $this->app->singleton(AiDriverManager::class);
        $this->app->singleton(ComposioClient::class);
        $this->app->singleton(McpToolProvider::class);
        $this->app->singleton(IntegrationManager::class);
    }
}
