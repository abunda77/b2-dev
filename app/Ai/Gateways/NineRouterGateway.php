<?php

namespace App\Ai\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\Providers\Provider;

class NineRouterGateway extends OpenRouterGateway
{
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return parent::client($provider, $timeout)
            ->withHeader('Accept', 'application/json');
    }
}
