<?php

namespace Easyreply\Inbox\Ai\Concerns;

use Easyreply\Inbox\Models\Conversation;
use Easyreply\Inbox\Models\Message;

/**
 * Shared prompt-building logic for AI reply drivers: a support-agent system
 * prompt and the conversation's message history reshaped into role/content
 * pairs, both OpenAI's and Anthropic's chat APIs expect.
 */
trait BuildsConversationContext
{
    protected function systemPrompt(): string
    {
        return 'You are a helpful, concise customer support agent. Draft a '
            .'polite reply to the customer\'s latest message using the '
            .'conversation history for context. Respond with only the reply '
            .'text — no preamble, no explanation.';
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function conversationHistory(Conversation $conversation): array
    {
        return $conversation->messages
            ->map(fn (Message $message) => [
                'role' => $message->direction === Message::DIRECTION_OUTBOUND ? 'assistant' : 'user',
                'content' => $message->body,
            ])
            ->all();
    }
}
