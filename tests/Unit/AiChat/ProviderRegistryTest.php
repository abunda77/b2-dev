<?php

namespace Tests\Unit\AiChat;

use App\Services\AiChat\ProviderRegistry;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderRegistryTest extends TestCase
{
    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProviderRegistry;
    }

    #[Test]
    public function available_providers_only_shows_providers_with_api_key(): void
    {
        Config::set('ai.providers', [
            'openai' => ['driver' => 'openai', 'key' => 'sk-test-key', 'url' => 'https://api.openai.com/v1'],
            'anthropic' => ['driver' => 'anthropic', 'key' => null, 'url' => 'https://api.anthropic.com/v1'],
            'gemini' => ['driver' => 'gemini', 'key' => '', 'url' => 'https://generativelanguage.googleapis.com/v1beta/'],
            '9router' => ['driver' => 'openai', 'key' => 'test-router-key', 'url' => 'https://api.9router.com/v1'],
        ]);

        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o']],
            'anthropic' => ['label' => 'Anthropic', 'models' => ['claude-sonnet-4-5']],
            'gemini' => ['label' => 'Gemini', 'models' => ['gemini-2.5-pro']],
            '9router' => ['label' => '9Router', 'models' => ['9router/ALIBABA100']],
        ]);

        $available = $this->registry->availableProviders();

        $this->assertCount(2, $available);
        $this->assertSame('openai', $available[0]['name']);
        $this->assertSame('9router', $available[1]['name']);
    }

    #[Test]
    public function ollama_is_always_available_even_without_key(): void
    {
        Config::set('ai.providers', [
            'ollama' => ['driver' => 'ollama', 'key' => '', 'url' => 'http://localhost:11434'],
        ]);

        Config::set('ai-chat.providers', [
            'ollama' => ['label' => 'Ollama', 'models' => ['llama3.2']],
        ]);

        $available = $this->registry->availableProviders();

        $this->assertCount(1, $available);
        $this->assertSame('ollama', $available[0]['name']);
    }

    #[Test]
    public function models_for_returns_configured_models(): void
    {
        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o', 'gpt-4o-mini']],
        ]);

        $models = $this->registry->modelsFor('openai');

        $this->assertSame(['gpt-4o', 'gpt-4o-mini'], $models);
    }

    #[Test]
    public function models_for_unknown_provider_returns_empty(): void
    {
        $models = $this->registry->modelsFor('nonexistent');

        $this->assertSame([], $models);
    }

    #[Test]
    public function default_provider_falls_back_to_first_available(): void
    {
        Config::set('ai-chat.default_provider', 'anthropic');

        Config::set('ai.providers', [
            'openai' => ['driver' => 'openai', 'key' => 'sk-test', 'url' => 'https://api.openai.com/v1'],
        ]);

        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o']],
        ]);

        $this->assertSame('openai', $this->registry->defaultProvider());
    }

    #[Test]
    public function default_model_for_returns_configured_default_if_available(): void
    {
        Config::set('ai-chat.default_model', 'gpt-4o-mini');

        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o', 'gpt-4o-mini']],
        ]);

        $this->assertSame('gpt-4o-mini', $this->registry->defaultModelFor('openai'));
    }

    #[Test]
    public function default_model_for_returns_first_model_when_default_not_in_list(): void
    {
        Config::set('ai-chat.default_model', 'o3-pro');

        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o', 'gpt-4o-mini']],
        ]);

        $this->assertSame('gpt-4o', $this->registry->defaultModelFor('openai'));
    }

    #[Test]
    public function is_available_checks_provider_key(): void
    {
        Config::set('ai.providers', [
            'openai' => ['driver' => 'openai', 'key' => 'sk-test', 'url' => 'https://api.openai.com/v1'],
            'anthropic' => ['driver' => 'anthropic', 'key' => null, 'url' => 'https://api.anthropic.com/v1'],
        ]);

        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o']],
            'anthropic' => ['label' => 'Anthropic', 'models' => ['claude-sonnet-4-5']],
        ]);

        $this->assertTrue($this->registry->isAvailable('openai'));
        $this->assertFalse($this->registry->isAvailable('anthropic'));
    }

    #[Test]
    public function ninerouter_provider_is_configurable_via_env(): void
    {
        Config::set('ai.providers', [
            '9router' => ['driver' => 'openai', 'key' => env('NINEROUTER_API_KEY'), 'url' => env('NINEROUTER_URL', 'https://api.9router.com/v1')],
        ]);

        Config::set('ai-chat.providers', [
            '9router' => ['label' => '9Router', 'models' => ['9router/ALIBABA100'], 'image_models' => []],
        ]);

        $models = $this->registry->modelsFor('9router');

        $this->assertContains('9router/ALIBABA100', $models);
    }

    #[Test]
    public function supports_images_returns_true_for_vision_models(): void
    {
        Config::set('ai-chat.providers', [
            'openai' => ['label' => 'OpenAI', 'models' => ['gpt-4o', 'gpt-4o-mini'], 'image_models' => ['gpt-4o']],
        ]);

        $this->assertTrue($this->registry->supportsImages('openai', 'gpt-4o'));
        $this->assertFalse($this->registry->supportsImages('openai', 'gpt-4o-mini'));
    }

    #[Test]
    public function supports_images_returns_false_when_no_image_models_configured(): void
    {
        Config::set('ai-chat.providers', [
            'deepseek' => ['label' => 'DeepSeek', 'models' => ['deepseek-v4-flash'], 'image_models' => []],
        ]);

        $this->assertFalse($this->registry->supportsImages('deepseek', 'deepseek-v4-flash'));
    }
}
