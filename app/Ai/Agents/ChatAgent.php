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

    public function __construct(public ?User $user = null) {}

    public function instructions(): Stringable|string
    {
        return config('ai-chat.system_prompt', 'You are a helpful AI assistant.');
    }
}
