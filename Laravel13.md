# Laravel 13 Feature Overview

## Introduction

Laravel 13, released on **March 17, 2026**, continues Laravel's annual release cadence with an emphasis on **AI-native development workflows**, **stronger framework defaults**, **expanded developer ergonomics**, and **minimal upgrade friction**. Most applications upgrading from Laravel 12 can move to Laravel 13 with relatively small code changes, while gaining access to several new first-party capabilities.

## Release Summary

- **Minimum PHP version:** 8.3 (up to 8.5)
- **Release date:** March 17, 2026
- **Bug fix support until:** Q3 2027
- **Security fix support until:** March 17, 2028
- **Upgrade profile:** Minimal breaking changes, primarily additive improvements

## Latest Features in Laravel 13

| Feature Name | Detailed Description | Key Improvements | Code Example / Usage |
|---|---|---|---|
| **Laravel AI SDK** | Laravel 13 introduces a first-party AI SDK that provides a unified, Laravel-native API for working with text generation, agents with tool calling, embeddings, audio generation, image generation, and vector store integrations. Includes `Image::of()->generate()` for visual generation, `Audio::of()->generate()` for text-to-speech, and `Str::of()->toEmbeddings()` for embedding generation from strings. Provider-agnostic across AI backends. | Unified AI abstraction, agent support, native embeddings workflow, image and audio generation APIs, provider-agnostic design. | `SalesCoach::make()->prompt('Analyze this sales transcript...')` |
| **First-Party JSON:API Resources** | Laravel 13 adds built-in support for JSON:API-compliant resources. These resources simplify the process of returning standardized API responses, including proper resource serialization, relationships, links, sparse fieldsets, and specification-compliant headers. | Easier standards-compliant API responses, cleaner resource serialization, built-in support for links and included relationships. | `UserResource::make($user)->response()->header('Content-Type', 'application/vnd.api+json')` |
| **Enhanced Request Forgery Protection** | CSRF middleware renamed from `VerifyCsrfToken` to `PreventRequestForgery`, now performing origin-aware request verification via `Sec-Fetch-Site` header. Legacy aliases `VerifyCsrfToken` and `ValidateCsrfToken` remain but should be updated. | Stronger request validation, origin-aware verification, improved default security posture without breaking existing CSRF token workflows. | `PreventRequestForgery::class` |
| **Queue Routing by Class** | Laravel 13 allows queue routing rules to be defined centrally by job class using `Queue::route(...)`. This makes it easier to manage queue and connection assignments without repeating configuration logic throughout the codebase. | Centralized queue configuration, cleaner job dispatching, easier operational management for multi-queue applications. | `Queue::route(ProcessPodcast::class, connection: 'redis', queue: 'podcasts')` |
| **Expanded PHP Attributes** | Laravel 13 significantly expands first-party PHP attribute support. Controller attributes: `#[Middleware]`, `#[Authorize]`. Queue job attributes: `#[Tries]`, `#[Backoff]`, `#[Timeout]`, `#[FailOnTimeout]`. Additional attributes for Eloquent, events, notifications, validation, testing, and resource serialization. | More expressive class-level configuration, reduced boilerplate, improved readability, broader attribute coverage across framework subsystems. | `#[Middleware('auth')]` and `#[Authorize('create', [Comment::class, 'post'])]` |
| **Cache TTL Extension with `Cache::touch()`** | Laravel 13 adds `Cache::touch(...)`, allowing applications to extend the time-to-live of an existing cache item without first retrieving and rewriting its value. This is useful for sliding expiration and session-like caching strategies. | Better cache efficiency, less read/write overhead, simpler sliding-expiration patterns. | `Cache::touch('report:summary', now()->addMinutes(10))` |
| **Eloquent Value Object Caching Control** | Eloquent 13 introduces the `withoutObjectCaching` property on custom casts. Set `public bool $withoutObjectCaching = true` in a cast class to disable default object caching, forcing a new instance on each attribute resolution. | Finer control over cast instance lifecycle, prevents stale object state, useful for value objects with external dependencies. | `class AsAddress implements CastsAttributes { public bool $withoutObjectCaching = true; }` |
| **Semantic / Vector Search Support** | Laravel 13 deepens support for semantic and vector-based search workflows. Native vector query support integrates with embeddings and PostgreSQL + `pgvector`, including `minSimilarity` threshold filtering. Supports both pre-computed embedding arrays and raw search strings. | Native vector similarity queries, `minSimilarity` threshold, easier AI search workflows, strong integration with embeddings and PostgreSQL ecosystems. | `DB::table('documents')->whereVectorSimilarTo('embedding', 'Best wineries in Napa Valley', minSimilarity: 0.4)->limit(10)->get()` |
| **Minimum PHP 8.3 Requirement** | Laravel 13 now requires PHP 8.3 or later (supports up to PHP 8.5). This aligns the framework with newer PHP language improvements and ongoing platform support expectations. While not an application feature in the UI sense, it is an important platform-level change for all Laravel 13 projects. | Access to newer PHP features, stronger baseline for performance and language capabilities, improved long-term support alignment. | `"php": "^8.3", "laravel/framework": "^13.0"` |
| **Minimal Breaking Changes Upgrade Path** | Laravel 13 is designed as a low-friction major release. The framework team focused heavily on minimizing breaking changes while continuing to deliver practical quality-of-life enhancements and new capabilities. Upgrade estimated at ~10 minutes for most applications. | Faster upgrades, lower migration risk, better continuity for existing Laravel 12 applications. | `composer require laravel/framework:^13.0 --update-with-all-dependencies` |

## Example Snippets

### Laravel AI SDK

```php
use App\Ai\Agents\SalesCoach;

$response = SalesCoach::make()->prompt('Analyze this sales transcript...');

return (string) $response;
```

#### AI Image Generation

```php
use Laravel\Ai\Image;

$image = Image::of('A donut sitting on the kitchen counter')->generate();
$rawContent = (string) $image;
```

#### AI Audio Generation (Text-to-Speech)

```php
use Laravel\Ai\Audio;

$audio = Audio::of('I love coding with Laravel.')->generate();
$rawContent = (string) $audio;
```

#### AI Embeddings from Strings

```php
use Illuminate\Support\Str;

$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings();
```

### First-Party JSON:API Resources

```php
return UserResource::make($user)
    ->response()
    ->header('Content-Type', 'application/vnd.api+json');
```

### Enhanced Request Forgery Protection

```php
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

->withMiddleware(function ($middleware) {
    $middleware->web(append: [
        PreventRequestForgery::class,
    ]);
})
```

### Queue Routing by Class

```php
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessPodcast;

Queue::route(ProcessPodcast::class, connection: 'redis', queue: 'podcasts');
```

### Expanded PHP Attributes

#### Controller & Authorization Attributes

```php
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class CommentController
{
    #[Middleware('subscribed')]
    #[Authorize('create', [Comment::class, 'post'])]
    public function store(Post $post)
    {
        // ...
    }
}
```

#### Queue Job Attributes

```php
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\FailOnTimeout;

#[Tries(3)]
#[Backoff(60)]
#[Timeout(120)]
#[FailOnTimeout]
class ProcessPodcast implements ShouldQueue
{
    // ...
}
```

### Cache TTL Extension with `Cache::touch()`

```php
use Illuminate\Support\Facades\Cache;

Cache::put('report:summary', $data, now()->addMinutes(10));

Cache::touch('report:summary', now()->addMinutes(10));
```

### Semantic / Vector Search Support

```php
use Illuminate\Support\Facades\DB;

// Raw string search
$documents = DB::table('documents')
    ->whereVectorSimilarTo('embedding', 'Best wineries in Napa Valley')
    ->limit(10)
    ->get();

// With minimum similarity threshold
$documents = DB::table('documents')
    ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.4)
    ->limit(10)
    ->get();

// Combined with Eloquent where clauses
$documents = Document::query()
    ->where('team_id', $user->team_id)
    ->whereVectorSimilarTo('embedding', $request->input('query'))
    ->limit(10)
    ->get();
```

### Minimum PHP 8.3 Requirement

```json
{
  "require": {
    "php": "^8.3",
    "laravel/framework": "^13.0"
  }
}
```

### Minimal Breaking Changes Upgrade Path

```bash
composer require laravel/framework:^13.0 --update-with-all-dependencies
```

## Upgrade Guide: Breaking Changes (12.x → 13.x)

### Cache & Session Key Name Generation

Laravel 13 mengubah separator dari underscore (`_`) menjadi hyphen (`-`) untuk prefix cache, Redis, dan session:

```php
// Laravel 12.x
Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_';
Str::slug(env('APP_NAME', 'laravel'), '_').'_database_';
Str::slug(env('APP_NAME', 'laravel'), '_').'_session';

// Laravel 13.x
Str::slug(env('APP_NAME', 'laravel')).'-cache-';
Str::slug(env('APP_NAME', 'laravel')).'-database-';
Str::slug(env('APP_NAME', 'laravel')).'-session';
```

> **Dampak:** Cache entries dan session dari Laravel 12 tidak akan terbaca di Laravel 13. Clear cache setelah upgrade.

### Pagination Bootstrap View Name Changes

Nama view pagination internal berubah untuk Bootstrap 3 default:

```text
// Laravel 12.x
pagination::default
pagination::simple-default

// Laravel 13.x
pagination::bootstrap-3
pagination::simple-bootstrap-3
```

### CSRF Middleware Renamed

`VerifyCsrfToken` → `PreventRequestForgery`. Middleware baru melakukan origin-aware verification via header `Sec-Fetch-Site`. Legacy aliases (`VerifyCsrfToken`, `ValidateCsrfToken`) tetap berfungsi sementara.

### Eloquent Value Object Caching

Custom cast class kini dapat menonaktifkan object caching dengan properti `withoutObjectCaching`:

```php
class AsAddress implements CastsAttributes
{
    public bool $withoutObjectCaching = true;
}
```

## Recommended Adoption Notes

1. **Upgrade PHP first** to version 8.3 or later before attempting a Laravel 13 upgrade.
2. **Clear cache after upgrade** — cache/session key naming uses hyphens now, not underscores.
3. **Update CSRF middleware references** from `VerifyCsrfToken` to `PreventRequestForgery` (legacy aliases still work temporarily).
4. **Evaluate the AI SDK** if your application includes chat, classification, summarization, embeddings, image/audio generation, search, or automation use cases.
5. **Consider JSON:API resources** for projects that need standards-based API responses.
6. **Use PHP attributes** — controller, authorization, queue (`#[Tries]`, `#[Backoff]`, `#[Timeout]`, `#[FailOnTimeout]`) where colocated metadata improves clarity.
7. **Review queue and cache infrastructure** to take advantage of queue routing and cache TTL extension.
8. **Adopt vector search** with `minSimilarity` threshold where semantic retrieval or recommendation systems are needed.
9. **Review custom casts** — evaluate `withoutObjectCaching` for value objects needing fresh instances per resolution.

## Conclusion

Laravel 13 is a pragmatic major release that introduces meaningful new capabilities without imposing a heavy upgrade burden. Its most important additions center around **AI integration** (agents, image, audio, embeddings), **API standardization** (JSON:API), **developer productivity** (expanded PHP attributes including queue attributes), **security hardening** (origin-aware CSRF via PreventRequestForgery), and **modern search capabilities** (vector search with minSimilarity). Upgrade in ~10 minutes — clear cache, update CSRF references, and review custom casts.

## Source

Official release notes: <https://laravel.com/docs/13.x/releases>
