<?php

use Easyreply\Inbox\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\Artisan;

it('registers the service provider and merges the package config', function () {
    expect(config('shared-inbox.channels.email.enabled'))->toBeTrue()
        ->and(config('shared-inbox.ai.driver'))->toBe('null');
});

it('registers the shared-inbox:install command', function () {
    expect(Artisan::all())->toHaveKey('shared-inbox:install')
        ->and(Artisan::all()['shared-inbox:install'])->toBeInstanceOf(InstallCommand::class);
});
