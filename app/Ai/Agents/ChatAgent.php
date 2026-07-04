<?php

namespace App\Ai\Agents;

use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class ChatAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public ?string $systemPrompt = null;

    public function __construct(public ?User $user = null) {}

    public function withSystemPrompt(?string $systemPrompt): self
    {
        $this->systemPrompt = filled($systemPrompt) ? trim((string) $systemPrompt) : null;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt ?: config('ai-chat.system_prompt', 'You are a helpful AI assistant.');
    }
}
