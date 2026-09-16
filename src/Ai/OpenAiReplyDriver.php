<?php

namespace Easyreply\Inbox\Ai;

use Easyreply\Inbox\Ai\Concerns\BuildsConversationContext;
use Easyreply\Inbox\Ai\Contracts\AiReplyDriver;
use Easyreply\Inbox\Ai\Data\DraftReplyData;
use Easyreply\Inbox\Models\Conversation;
use Illuminate\Support\Facades\Http;

/**
 * Reference AiReplyDriver implementation using OpenAI's Chat Completions
 * API. Equally-supported alongside AnthropicReplyDriver — pick either via
 * config('shared-inbox.ai.driver'), or implement AiReplyDriver yourself for
 * any other provider.
 */
class OpenAiReplyDriver implements AiReplyDriver
{
    use BuildsConversationContext;

    public function draftReply(Conversation $conversation): ?DraftReplyData
    {
        $apiKey = config('shared-inbox.ai.openai.api_key');

        if (! $apiKey) {
            return null;
        }

        $response = Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('shared-inbox.ai.openai.model', 'gpt-4o-mini'),
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $this->systemPrompt()]],
                    $this->conversationHistory($conversation),
                ),
            ]);

        if ($response->failed()) {
            return null;
        }

        $body = trim((string) $response->json('choices.0.message.content'));

        return $body === '' ? null : new DraftReplyData(body: $body);
    }
}
