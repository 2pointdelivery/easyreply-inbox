<?php

namespace Easyreply\Inbox\Tests;

use Easyreply\Inbox\SharedInboxServiceProvider;
use Easyreply\Inbox\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            \Inertia\ServiceProvider::class,
            SharedInboxServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The package's root Inertia view (resources/views/app.blade.php)
        // uses @vite, but there's no real host-app asset build in package
        // tests — Inertia response assertions only need the server-side
        // props/component name, not compiled assets.
        $this->withoutVite();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Inertia's assertInertia() checks that the named page component
        // actually exists on disk. Point it at the package's own
        // resources/js/Pages (there's no host app's resources/js/Pages in
        // package tests) so the assertion is genuine, not disabled.
        $app['config']->set('inertia.testing.page_paths', [__DIR__.'/../resources/js/Pages']);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Standard `users` table, standing in for the host app's own users
        // migration — the package never owns this table (see
        // BUILD_PROMPT.md §11). Registered via loadMigrationsFrom (not
        // Testbench's loadLaravelMigrations(), which runs immediately and
        // gets wiped out by RefreshDatabase's later `migrate:fresh`) so it's
        // part of the same migration run as the package's own tables below.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // The package's own migrations are published (not auto-run) in a
        // real app — see SharedInboxServiceProvider — so tests load them
        // explicitly here instead.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
