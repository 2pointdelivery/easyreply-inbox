<?php

namespace Easyreply\Inbox\Ai;

use Easyreply\Inbox\Ai\Contracts\AiReplyDriver;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves the configured AiReplyDriver (config('shared-inbox.ai.driver')),
 * looking up its class in config('shared-inbox.ai.drivers'). Defaults to
 * 'null' (NullAiDriver) so the package works with zero AI config.
 */
class AiDriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->container->make('config')->get('shared-inbox.ai.driver', 'null');
    }

    public function createDriver($driver): AiReplyDriver
    {
        $class = $this->container->make('config')->get("shared-inbox.ai.drivers.{$driver}");

        if (! $class) {
            throw new InvalidArgumentException("AI driver [{$driver}] is not configured in config('shared-inbox.ai.drivers').");
        }

        return $this->container->make($class);
    }
}
