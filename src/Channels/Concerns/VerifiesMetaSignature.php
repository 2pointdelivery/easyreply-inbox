<?php

namespace Easyreply\Inbox\Channels\Concerns;

use Illuminate\Http\Request;

/**
 * Meta (WhatsApp Cloud API / Instagram Messaging) signs webhook deliveries
 * with an X-Hub-Signature-256 header: "sha256=" + HMAC-SHA256 of the raw
 * request body using the app secret. Shared by WhatsAppChannelDriver and
 * InstagramChannelDriver since both sit behind the same Meta webhook scheme.
 */
trait VerifiesMetaSignature
{
    protected function verifyMetaSignature(Request $request, ?string $appSecret): bool
    {
        if (! $appSecret) {
            // No secret configured: nothing to verify against. The host app
            // is expected to set the app secret before exposing this
            // endpoint publicly.
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $computed = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($computed, $signature);
    }
}
