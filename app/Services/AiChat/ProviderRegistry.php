<?php

namespace App\Services\AiChat;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ProviderRegistry
{
    /**
     * Get all providers that have a configured API key, with labels and models.
     *
     * @return Collection<int, array{name: string, label: string, models: array<int, string>, has_key: bool}>
     */
    public function availableProviders(): Collection
    {
        $aiProviders = config('ai.providers', []);
        $chatProviders = config('ai-chat.providers', []);

        if (! is_array($chatProviders) || ! is_array($aiProviders)) {
            return new Collection;
        }

        $available = [];

        foreach ($chatProviders as $name => $chatConfig) {
            if (! is_string($name) || ! is_array($chatConfig)) {
                continue;
            }

            $label = is_string($chatConfig['label'] ?? null) ? $chatConfig['label'] : $name;
            $models = $this->normalizeModels($chatConfig['models'] ?? []);

            $aiConfig = Arr::get($aiProviders, $name, []);
            $hasKey = filled(Arr::get($aiConfig, 'key'));

            if ($name === 'ollama') {
                $hasKey = true;
            }

            if (! $hasKey) {
                continue;
            }

            $available[] = [
                'name' => $name,
                'label' => $label,
                'models' => $models,
                'has_key' => $hasKey,
            ];
        }

        return new Collection($available);
    }

    /**
     * Get the models available for a given provider name.
     *
     * @return array<int, string>
     */
    public function modelsFor(string $provider): array
    {
        $models = config("ai-chat.providers.{$provider}.models", []);

        return $this->normalizeModels($models);
    }

    /**
     * Get the default provider name.
     */
    public function defaultProvider(): string
    {
        $default = config('ai-chat.default_provider', 'openai');

        if (! is_string($default)) {
            $default = 'openai';
        }

        $availableNames = $this->availableProviders()->pluck('name')->all();

        return in_array($default, $availableNames, true) ? $default : ($availableNames[0] ?? 'openai');
    }

    /**
     * Get the default model for a given provider.
     */
    public function defaultModelFor(string $provider): string
    {
        $configuredDefault = config('ai-chat.default_model', '');

        if (! is_string($configuredDefault)) {
            $configuredDefault = '';
        }

        $models = $this->modelsFor($provider);

        if ($configuredDefault !== '' && in_array($configuredDefault, $models, true)) {
            return $configuredDefault;
        }

        return $models[0] ?? '';
    }

    /**
     * Check if a provider name is available (has key configured).
     */
    public function isAvailable(string $provider): bool
    {
        return $this->availableProviders()->where('name', $provider)->isNotEmpty();
    }

    /**
     * Check if the given model supports image (multimodal) input.
     */
    public function supportsImages(string $provider, string $model): bool
    {
        $imageModels = config("ai-chat.providers.{$provider}.image_models", []);

        if (! is_array($imageModels)) {
            return false;
        }

        return in_array($model, $imageModels, true);
    }

    /**
     * Normalize a models configuration value into a typed string array.
     *
     * @param  mixed  $models
     * @return array<int, string>
     */
    private function normalizeModels($models): array
    {
        if (! is_array($models)) {
            return [];
        }

        return array_values(array_filter($models, 'is_string'));
    }
}
