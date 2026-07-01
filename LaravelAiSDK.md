# Laravel AI SDK

## Overview

Laravel AI SDK adalah SDK AI resmi dari Laravel 13 untuk integrasi model AI dengan API yang konsisten, ekspresif, dan provider-agnostic. SDK ini mendukung **text generation**, **agent orchestration**, **tool calling**, **structured output**, **image generation**, **text-to-speech (TTS)**, **speech-to-text (STT)**, **embeddings**, **reranking**, **file handling**, **vector stores**, **streaming**, **queueing**, dan **testing utilities**.

Dokumentasi ini dirangkum dari dokumentasi resmi Laravel 13 dan referensi terbaru Context7 agar fokus pada praktik implementasi modern.

## Highlights

- API terpadu untuk banyak provider AI
- Agent berbasis class PHP
- Tool calling lokal, provider tools, dan MCP tools
- Structured output berbasis JSON schema
- Built-in conversation persistence
- Streaming response dan broadcasting
- Dukungan queue untuk background inference
- Embeddings, reranking, vector stores, dan file search
- Fasilitas testing bawaan

## Installation

Install paket:

```bash
composer require laravel/ai
```

Publish konfigurasi dan migration:

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

Jalankan migration:

```bash
php artisan migrate
```

Migration ini membuat tabel:

- `agent_conversations`
- `agent_conversation_messages`

## Configuration

Atur kredensial provider di `.env` atau `config/ai.php`.

```env
ANTHROPIC_API_KEY=
AZURE_OPENAI_API_KEY=
COHERE_API_KEY=
DEEPSEEK_API_KEY=
ELEVENLABS_API_KEY=
GEMINI_API_KEY=
GROQ_API_KEY=
MISTRAL_API_KEY=
OLLAMA_API_KEY=
OPENAI_API_KEY=
OPENROUTER_API_KEY=
JINA_API_KEY=
VOYAGEAI_API_KEY=
XAI_API_KEY=
```

Laravel AI SDK juga mendukung custom base URL untuk proxy atau gateway internal:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL'),
    ],

    'anthropic' => [
        'driver' => 'anthropic',
        'key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_BASE_URL'),
    ],
],
```

## Supported Provider Matrix

| Capability | Supported Providers |
|---|---|
| Text | OpenAI, Anthropic, Gemini, Azure, Bedrock, Groq, xAI, DeepSeek, Mistral, Ollama, OpenRouter |
| Images | OpenAI, Gemini, xAI, Azure, Bedrock, OpenRouter |
| TTS | OpenAI, ElevenLabs, Gemini |
| STT | OpenAI, ElevenLabs, Mistral, Gemini |
| Embeddings | OpenAI, Gemini, Azure, Bedrock, Cohere, Mistral, Jina, VoyageAI, Ollama, OpenRouter |
| Reranking | Cohere, Jina, VoyageAI |
| Files | OpenAI, Anthropic, Gemini |

Enum `Laravel\Ai\Enums\Lab` dapat dipakai untuk referensi provider yang type-safe:

```php
use Laravel\Ai\Enums\Lab;

Lab::Anthropic;
Lab::OpenAI;
Lab::Gemini;
```

## Core Concepts

### 1. Agents

Agent adalah unit utama untuk berinteraksi dengan model AI. Agent menyimpan:

- instruction / system prompt
- conversation context
- tools
- structured output schema
- provider / model config

Buat agent baru:

```bash
php artisan make:agent SalesCoach
php artisan make:agent SalesCoach --structured
```

Contoh agent lengkap:

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Tools\RetrievePreviousTranscripts;
use App\Models\History;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class SalesCoach implements Agent, Conversational, HasTools, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user) {}

    public function instructions(): Stringable|string
    {
        return 'You are a sales coach, analyzing transcripts and providing feedback and an overall sales strength score.';
    }

    public function messages(): iterable
    {
        return History::where('user_id', $this->user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->map(fn ($message) => new Message($message->role, $message->content))
            ->all();
    }

    public function tools(): iterable
    {
        return [
            new RetrievePreviousTranscripts,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()->required(),
            'score' => $schema->integer()->min(1)->max(10)->required(),
        ];
    }
}
```

### 2. Prompting

Prompt agent:

```php
use App\Ai\Agents\SalesCoach;

$response = SalesCoach::make(user: $user)
    ->prompt('Analyze this sales transcript...');

return (string) $response;
```

Override provider, model, timeout saat runtime:

```php
use Laravel\Ai\Enums\Lab;

$response = (new SalesCoach($user))->prompt(
    'Analyze this sales transcript...',
    provider: Lab::Anthropic,
    model: 'claude-haiku-4-5-20251001',
    timeout: 120,
);
```

## Conversation Memory

Jika butuh persistence otomatis, gunakan trait `RemembersConversations`.

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class SalesCoach implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return 'You are a sales coach...';
    }
}
```

Mulai percakapan baru:

```php
$response = (new SalesCoach)
    ->forUser($user)
    ->prompt('Hello!');

$conversationId = $response->conversationId;
```

Lanjutkan percakapan lama:

```php
$response = (new SalesCoach)
    ->continue($conversationId, as: $user)
    ->prompt('Tell me more about that.');
```

Tambahkan trait `HasConversations` ke model user jika ingin query relasi conversation:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Ai\Concerns\HasConversations;

class User extends Authenticatable
{
    use HasConversations;
}
```

## Structured Output

Structured output cocok untuk data yang harus stabil dan mudah diproses backend.

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class SalesCoach implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Analyze the transcript and return a score.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->required(),
            'summary' => $schema->string()->required(),
        ];
    }
}
```

Akses hasil seperti array:

```php
$response = (new SalesCoach)->prompt('Analyze this sales transcript...');

return $response['score'];
```

Contoh nested object:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'score' => $schema->integer()->required(),
        'metadata' => $schema->object(fn ($schema) => [
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'language' => $schema->string()->required(),
        ])->required(),
    ];
}
```

Contoh array of objects:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'feedback' => $schema->array()
            ->items(
                $schema->object(fn ($schema) => [
                    'comment' => $schema->string()->required(),
                    'score' => $schema->integer()->required(),
                ])
            )
            ->required(),
    ];
}
```

## Attachments

Agent bisa menerima dokumen dan gambar sebagai attachment.

Dokumen:

```php
use Laravel\Ai\Files;

$response = (new SalesCoach)->prompt(
    'Analyze the attached sales transcript...',
    attachments: [
        Files\Document::fromStorage('transcript.pdf'),
        Files\Document::fromPath('/home/laravel/transcript.md'),
        $request->file('transcript'),
    ]
);
```

Gambar:

```php
use Laravel\Ai\Files;

$response = (new ImageAnalyzer)->prompt(
    'What is in this image?',
    attachments: [
        Files\Image::fromStorage('photo.jpg'),
        Files\Image::fromPath('/home/laravel/photo.jpg'),
        $request->file('photo'),
    ]
);
```

## Streaming

Streaming cocok untuk chat UI, live analysis, dan partial rendering.

```php
use App\Ai\Agents\SalesCoach;

Route::get('/coach', function () {
    return (new SalesCoach)->stream('Analyze this sales transcript...');
});
```

Hook setelah stream selesai:

```php
use Laravel\Ai\Responses\StreamedAgentResponse;

Route::get('/coach', function () {
    return (new SalesCoach)
        ->stream('Analyze this sales transcript...')
        ->then(function (StreamedAgentResponse $response) {
            // $response->text, $response->events, $response->usage
        });
});
```

Manual iterate:

```php
$stream = (new SalesCoach)->stream('Analyze this sales transcript...');

foreach ($stream as $event) {
    // handle event
}
```

Pakai protokol Vercel AI SDK:

```php
Route::get('/coach', function () {
    return (new SalesCoach)
        ->stream('Analyze this sales transcript...')
        ->usingVercelDataProtocol();
});
```

## Broadcasting

Broadcast event hasil stream:

```php
use Illuminate\Broadcasting\Channel;

$stream = (new SalesCoach)->stream('Analyze this sales transcript...');

foreach ($stream as $event) {
    $event->broadcast(new Channel('channel-name'));
}
```

Atau queue + broadcast otomatis:

```php
use Illuminate\Broadcasting\Channel;

(new SalesCoach)->broadcastOnQueue(
    'Analyze this sales transcript...',
    new Channel('channel-name'),
);
```

## Queueing

Inference bisa dipindah ke background job.

```php
use Illuminate\Http\Request;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

Route::post('/coach', function (Request $request) {
    (new SalesCoach)
        ->queue($request->input('transcript'))
        ->then(function (AgentResponse $response) {
            // handle success
        })
        ->catch(function (Throwable $e) {
            // handle failure
        });

    return back();
});
```

## Tools

### Custom Tools

Buat tool:

```bash
php artisan make:tool RandomNumberGenerator
```

Contoh implementasi:

```php
<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RandomNumberGenerator implements Tool
{
    public function description(): Stringable|string
    {
        return 'This tool may be used to generate cryptographically secure random numbers.';
    }

    public function handle(Request $request): Stringable|string
    {
        return (string) random_int($request['min'], $request['max']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->min(0)->required(),
            'max' => $schema->integer()->required(),
        ];
    }
}
```

Daftarkan di agent:

```php
public function tools(): iterable
{
    return [
        new RandomNumberGenerator,
    ];
}
```

### Similarity Search Tool

Untuk RAG berbasis embedding database:

```php
use App\Models\Document;
use Laravel\Ai\Tools\SimilaritySearch;

public function tools(): iterable
{
    return [
        SimilaritySearch::usingModel(Document::class, 'embedding'),
    ];
}
```

Dengan threshold, limit, dan query filter:

```php
SimilaritySearch::usingModel(
    model: Document::class,
    column: 'embedding',
    minSimilarity: 0.7,
    limit: 10,
    query: fn ($query) => $query->where('published', true),
)
```

Dengan custom closure:

```php
new SimilaritySearch(using: function (string $query) {
    return Document::query()
        ->where('user_id', $this->user->id)
        ->whereVectorSimilarTo('embedding', $query)
        ->limit(10)
        ->get();
})
```

## MCP Tools

Jika app pakai Laravel MCP, tools dari MCP server bisa langsung dipakai agent.

```php
use Laravel\Mcp\Client;

public function tools(): iterable
{
    return [
        ...Client::web('https://mcp.example.com')
            ->withToken($token)
            ->tools(),
    ];
}
```

Named client:

```php
use Laravel\Mcp\Facades\Mcp;

public function tools(): iterable
{
    return [
        ...Mcp::client('github')->tools(),
    ];
}
```

Local MCP server:

```php
use Laravel\Mcp\Client;

public function tools(): iterable
{
    return [
        ...Client::local('php', ['artisan', 'mcp:start'])->tools(),
    ];
}
```

## Provider Tools

Provider tools dijalankan langsung oleh provider AI, bukan aplikasi Anda.

### Web Search

Supported providers: Anthropic, OpenAI, Gemini

```php
use Laravel\Ai\Providers\Tools\WebSearch;

public function tools(): iterable
{
    return [
        new WebSearch,
    ];
}
```

Batasi jumlah hasil dan domain:

```php
(new WebSearch)->max(5)->allow(['laravel.com', 'php.net']);
```

Atur lokasi:

```php
(new WebSearch)->location(
    city: 'New York',
    region: 'NY',
    country: 'US'
);
```

### Web Fetch

Supported providers: Anthropic, Gemini

```php
use Laravel\Ai\Providers\Tools\WebFetch;

public function tools(): iterable
{
    return [
        new WebFetch,
    ];
}
```

```php
(new WebFetch)->max(3)->allow(['docs.laravel.com']);
```

### File Search

Supported providers: OpenAI, Gemini

```php
use Laravel\Ai\Providers\Tools\FileSearch;

public function tools(): iterable
{
    return [
        new FileSearch(stores: ['store_id']),
    ];
}
```

Multi store:

```php
new FileSearch(stores: ['store_1', 'store_2']);
```

Filter sederhana:

```php
new FileSearch(stores: ['store_id'], where: [
    'author' => 'Taylor Otwell',
    'year' => 2026,
]);
```

Filter kompleks:

```php
use Laravel\Ai\Providers\Tools\FileSearchQuery;

new FileSearch(stores: ['store_id'], where: fn (FileSearchQuery $query) =>
    $query->where('author', 'Taylor Otwell')
        ->whereNot('status', 'draft')
        ->whereIn('category', ['news', 'updates'])
);
```

## Sub-Agents

Agent dapat dipakai sebagai tool oleh agent lain.

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class CustomerSupportAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You help customers with account, order, and billing questions. Delegate refund policy questions to the refunds specialist.';
    }

    public function tools(): iterable
    {
        return [
            new RefundsAgent,
        ];
    }
}
```

Kustom nama dan deskripsi tool-facing dengan `CanActAsTool`:

```php
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
class RefundsAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a refunds specialist.';
    }

    public function name(): string
    {
        return 'refunds_specialist';
    }

    public function description(): string
    {
        return 'Determine whether an order is eligible for a refund and explain the next step.';
    }

    public function tools(): iterable
    {
        return [];
    }
}
```

## Agent Middleware

Middleware dipakai untuk inspeksi, logging, guardrails, atau mutasi prompt.

Buat middleware:

```bash
php artisan make:agent-middleware LogPrompts
```

Daftarkan di agent:

```php
use App\Ai\Middleware\LogPrompts;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

class SalesCoach implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach.';
    }

    public function middleware(): array
    {
        return [
            new LogPrompts,
        ];
    }
}
```

Implementasi middleware:

```php
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Illuminate\Support\Facades\Log;

class LogPrompts
{
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        Log::info('Prompting agent', ['prompt' => $prompt->prompt]);

        return $next($prompt)->then(function (AgentResponse $response) {
            Log::info('Agent responded', ['text' => $response->text]);
        });
    }
}
```

## Anonymous Agents

Untuk use case cepat tanpa class terpisah:

```php
use function Laravel\Ai\{agent};

$response = agent(
    instructions: 'You are an expert at software development.',
    messages: [],
    tools: [],
)->prompt('Tell me about Laravel');
```

Anonymous agent dengan structured output:

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use function Laravel\Ai\{agent};

$response = agent(
    schema: fn (JsonSchema $schema) => [
        'number' => $schema->integer()->required(),
    ],
)->prompt('Generate a random number less than 100');
```

## Agent Configuration via Attributes

Laravel AI SDK menyediakan attribute konfigurasi berikut:

- `MaxSteps`
- `MaxTokens`
- `Model`
- `Provider`
- `Temperature`
- `Timeout`
- `TopP`
- `UseCheapestModel`
- `UseSmartestModel`

Contoh:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\TopP;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]
#[MaxSteps(10)]
#[MaxTokens(4096)]
#[Temperature(0.7)]
#[Timeout(120)]
#[TopP(0.9)]
class SalesCoach implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach.';
    }
}
```

## Images

Generate image dari prompt teks:

```php
use Laravel\Ai\Image;

$image = Image::of('A donut sitting on the kitchen counter')->generate();

$rawContent = (string) $image;
```

## Audio (TTS)

Generate audio dari teks:

```php
use Laravel\Ai\Audio;

$audio = Audio::of('I love coding with Laravel.')->generate();

$rawContent = (string) $audio;
```

## Transcription (STT)

Gunakan fitur transcription untuk mengubah audio menjadi teks. Capability ini didukung oleh OpenAI, ElevenLabs, Mistral, dan Gemini. Implementasi spesifik tergantung provider dan model yang dipilih di `config/ai.php`.

## Embeddings

Generate embedding dari string:

```php
use Illuminate\Support\Str;

$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings();
```

### Querying Embeddings

Laravel 13 mendukung query vector similarity langsung dari query builder:

```php
use Illuminate\Support\Facades\DB;

$documents = DB::table('documents')
    ->whereVectorSimilarTo('embedding', 'Best wineries in Napa Valley')
    ->limit(10)
    ->get();
```

### Caching Embeddings

Embedding dapat di-cache untuk mengurangi biaya inference berulang. Strategi implementasi umumnya memakai `Cache` pada teks input yang identik sebelum memanggil provider embedding.

## Reranking

Laravel AI SDK mendukung reranking untuk meningkatkan urutan hasil retrieval setelah similarity search awal. Capability ini didukung oleh Cohere, Jina, dan VoyageAI.

## Files and Vector Stores

AI SDK mendukung file upload/management dan vector store untuk retrieval workflow, termasuk file search provider tool. Ini cocok untuk RAG, knowledge base, document Q&A, dan domain-specific assistants.

## Failover

AI SDK mendukung strategi provider failover melalui konfigurasi provider pada agent. Ini berguna untuk reliabilitas tinggi, fallback biaya, atau fallback region/provider saat request utama gagal.

## Testing

Laravel AI SDK menyediakan testing support untuk:

- Agents
- Images
- Audio
- Transcriptions
- Embeddings
- Reranking
- Files
- Vector Stores

Praktik terbaik:

- mock response provider saat unit test
- fokuskan assertion pada structured output atau side effects
- pisahkan integration test yang benar-benar memanggil provider eksternal
- gunakan fake/stub saat menguji workflow queue, broadcast, dan persistence conversation

## Recommended Use Cases

| Use Case | Fitur Utama |
|---|---|
| Chat assistant internal | Agents, memory, streaming |
| Customer support automation | Agents, tools, sub-agents, queueing |
| Sales transcript analysis | Structured output, attachments, memory |
| Knowledge base / RAG | Embeddings, similarity search, file search, vector stores |
| AI content enrichment | Text generation, reranking, queueing |
| Multi-provider resilience | Provider attributes, failover |
| Voice assistant | TTS, STT, streaming |

## Best Practices

1. Gunakan **structured output** untuk workflow backend yang butuh data stabil.
2. Simpan **conversation ID** jika ingin pengalaman chat berkelanjutan.
3. Pakai **queueing** untuk inference berat atau latensi tinggi.
4. Gunakan **streaming** untuk UX real-time.
5. Pisahkan **tools sempit dan jelas** agar agent lebih konsisten.
6. Batasi domain pada **provider tools** seperti `WebSearch` dan `WebFetch` bila relevan.
7. Pakai **SimilaritySearch** atau `whereVectorSimilarTo()` untuk RAG berbasis data internal.
8. Atur **provider, model, timeout, dan token budget** per agent, bukan global semua kasus.
9. Tambahkan **middleware** untuk logging, policy, audit, dan observability.
10. Siapkan **testing fake/stub** sebelum AI dipakai di alur bisnis kritis.

## Source References

- Official Laravel 13 AI SDK docs: <https://laravel.com/docs/13.x/ai-sdk>
- Official Laravel 13 release notes: <https://laravel.com/docs/13.x/releases>
- Context7 library: `/laravel/docs/__branch__13.x`
