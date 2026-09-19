<?php

namespace Easyreply\Inbox\Voice;

use Easyreply\Inbox\Voice\Contracts\VoiceAgentDriver;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves the configured VoiceAgentDriver (config('shared-inbox.voice.driver')),
 * looking up its class in config('shared-inbox.voice.drivers'). Defaults to
 * 'null' (NullVoiceDriver) so the package works with zero voice config.
 */
class VoiceAgentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->container->make('config')->get('shared-inbox.voice.driver', 'null');
    }

    public function createDriver($driver): VoiceAgentDriver
    {
        $class = $this->container->make('config')->get("shared-inbox.voice.drivers.{$driver}");

        if (! $class) {
            throw new InvalidArgumentException("Voice driver [{$driver}] is not configured in config('shared-inbox.voice.drivers').");
        }

        return $this->container->make($class);
    }
}
