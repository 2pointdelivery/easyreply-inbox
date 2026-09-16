<?php

namespace Easyreply\Inbox\Ai;

use Easyreply\Inbox\Ai\Concerns\BuildsConversationContext;
use Easyreply\Inbox\Ai\Contracts\AiReplyDriver;
use Easyreply\Inbox\Ai\Data\DraftReplyData;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Reference AiReplyDriver implementation using Anthropic's Messages API.
 * Equally-supported alongside OpenAiReplyDriver — pick either via
 * config('shared-inbox.ai.driver'), or implement AiReplyDriver yourself for
 * any other provider.
 */
class AnthropicReplyDriver implements AiReplyDriver
{
    use BuildsConversationContext;

    public function draftReply(Conversation $conversation): ?DraftReplyData
    {
        $apiKey = config('shared-inbox.ai.anthropic.api_key');

        if (! $apiKey) {
            return null;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => config('shared-inbox.ai.anthropic.model', 'claude-3-5-haiku-latest'),
            'max_tokens' => 1024,
            'system' => $this->systemPrompt(),
            'messages' => $this->conversationHistory($conversation),
        ]);

        if ($response->failed()) {
            return null;
        }

        $body = trim((string) $response->json('content.0.text'));

        return $body === '' ? null : new DraftReplyData(body: $body);
    }
}
