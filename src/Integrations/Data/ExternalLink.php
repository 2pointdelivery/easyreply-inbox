<?php

namespace Easyreply\Inbox\Integrations\Data;

final class ExternalLink
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalId,
        public readonly string $url,
    ) {}
}
