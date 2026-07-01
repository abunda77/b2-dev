<?php

namespace App\Ai\Providers;

use App\Ai\Gateways\NineRouterGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\EmbeddingGateway;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\Providers\OpenRouterProvider;

class NineRouterProvider extends OpenRouterProvider
{
    public function __construct(array $config, Dispatcher $events)
    {
        parent::__construct($config, $events);
    }

    public function textGateway(): TextGateway
    {
        return $this->textGateway ??= new NineRouterGateway($this->events);
    }

    public function embeddingGateway(): EmbeddingGateway
    {
        return $this->embeddingGateway ??= new OpenRouterGateway($this->events);
    }
}
