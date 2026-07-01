<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Chat Settings
    |--------------------------------------------------------------------------
    |
    | These values are used as defaults when no provider or model is specified
    | by the user in the chat interface. The provider name must match a key
    | in config/ai.php providers.
    |
    */

    'default_provider' => env('AI_CHAT_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('AI_CHAT_DEFAULT_MODEL', 'gpt-4o'),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | The system prompt (instructions) sent to the AI model. This defines
    | the assistant's personality and behavior across all chat sessions.
    |
    */

    'system_prompt' => env('AI_CHAT_SYSTEM_PROMPT', 'You are a helpful AI assistant. Answer questions accurately and concisely. Format responses using Markdown when helpful.'),

    /*
    |--------------------------------------------------------------------------
    | Provider & Model Registry
    |--------------------------------------------------------------------------
    |
    | Lists available providers and their models for the chat interface.
    | Providers without a configured API key are automatically hidden.
    | 'label' is the display name shown in the UI dropdown.
    | 'models' lists the available models for each provider.
    |
    */

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'o3-mini', 'o4-mini'],
            'image_models' => ['gpt-4o'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'models' => ['claude-sonnet-4-5-20250929', 'claude-haiku-4-5-20251001', 'claude-opus-4-5-20251101'],
            'image_models' => ['claude-sonnet-4-5-20250929', 'claude-haiku-4-5-20251001', 'claude-opus-4-5-20251101'],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'models' => ['gemini-2.5-pro', 'gemini-2.5-flash'],
            'image_models' => ['gemini-2.5-pro', 'gemini-2.5-flash'],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'models' => ['deepseek-v4-flash', 'deepseek-v4-pro'],
            'image_models' => [],
        ],
        'groq' => [
            'label' => 'Groq',
            'models' => ['openai/gpt-oss-20b', 'qwen/qwen3-32b'],
            'image_models' => [],
        ],
        'mistral' => [
            'label' => 'Mistral',
            'models' => ['mistral-large-latest', 'mistral-small-latest'],
            'image_models' => ['mistral-large-latest'],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'models' => ['openai/gpt-4o', 'anthropic/claude-sonnet-4-5', 'google/gemini-2.5-pro'],
            'image_models' => ['openai/gpt-4o', 'anthropic/claude-sonnet-4-5', 'google/gemini-2.5-pro'],
        ],
        '9router' => [
            'label' => '9Router',
            'models' => array_values(array_filter(
                explode(',', (string) env('NINEROUTER_MODELS', '9router/ALIBABA100')),
                fn (string $m) => trim($m) !== ''
            )),
            'image_models' => [],
        ],
        'xai' => [
            'label' => 'xAI (Grok)',
            'models' => ['grok-3'],
            'image_models' => ['grok-3'],
        ],
        'ollama' => [
            'label' => 'Ollama (Local)',
            'models' => ['llama3.2', 'mistral', 'gemma3'],
            'image_models' => ['llama3.2', 'gemma3'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment Validation
    |--------------------------------------------------------------------------
    |
    | Validation rules for file attachments in the chat.
    |
    */

    'attachments' => [
        'max_files' => 5,
        'max_size_kb' => 10240,
        'allowed_image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'allowed_document_mimes' => ['pdf', 'txt', 'md', 'csv', 'json', 'xml', 'doc', 'docx'],
    ],
];
