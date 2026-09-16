<?php

namespace Easyreply\Inbox\Channels;

use Easyreply\Inbox\Channels\Contracts\ChannelDriver;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves a ChannelDriver by channel_type (email, slack, whatsapp,
 * instagram) using the class configured in config('shared-inbox.channels').
 * Each channel must be explicitly enabled in config to be resolvable.
 */
class ChannelManager extends Manager
{
    public function getDefaultDriver(): string
    {
        throw new InvalidArgumentException(
            'No default channel driver — resolve a specific one via ChannelManager::driver($channelType).'
        );
    }

    public function createDriver($driver): ChannelDriver
    {
        $config = $this->container->make('config')->get("shared-inbox.channels.{$driver}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            throw new InvalidArgumentException("Channel [{$driver}] is not enabled in config('shared-inbox.channels').");
        }

        return $this->container->make($config['driver']);
    }
}
