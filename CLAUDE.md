# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

**Setup:**
- First-run: `composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate`

**Development:**
- Run Vite dev server + hot reload: `npm run dev` (or `composer run dev`)
- Rebuild assets: `npm run build`
- Full first-run setup: `composer run setup`

**Code Quality:**
- Format code: `vendor/bin/pint --dirty --format agent` (after editing PHP files)
- Run Pint tests: `vendor/bin/pint --parallel --test` (never needed)

**Testing:**
- Run all tests: `php artisan test --compact` (or `composer run test`)
- Run specific test: `php artisan test --compact tests/Feature/ExampleTest.php --filter=testName`
- Run single test: `php artisan test tests/Feature/ExampleTest.php::testName --compact`
- Create test: `php artisan make:test --phpunit {name}`

**Static Analysis:**
- Run PHPStan: `php artisan types:check` or `composer run types:check`

**Queue (required for OTP delivery):**
- Worker must listen on the `otp` queue or OTP codes will never send: `php artisan queue:work --queue=otp,default`
- `QUEUE_CONNECTION=database` in prod; `sync` is fine for dev/test.

**Artisan:**
- Use `php artisan list` to see commands, `php artisan [command] --help` for params
- `php artisan route:list --except-vendor` to show web routes
- Filter routes: `php artisan route:list --method=GET --name=users --path=api`

## Architecture

**Tech Stack:**
- Laravel 13.x + PHP 8.3
- Livewire 4.x + TailwindCSS 4 + Flux UI (Frontend: Vite + Alpine.js)
- Storage: S3-compatible (Backblaze B2, Cloudflare R2, or AWS S3)
- Database: SQLite (dev), PostgreSQL/MySQL (prod)
- Auth: Laravel Fortify + Passkeys
- Testing: PHPUnit 12.x
- Code Quality: Laravel Pint, Larastan

**Directory Structure:**
```
app/
├── Actions/       # Use-case based class groups for Livewire logic
├── Concerns/      # Shared logic traits
├── Console/       # Artisan commands
├── Http/          # Controllers, request classes, form requests
├── Livewire/      # Components (use well-structured Actions pattern)
├── Models/        # Eloquent models (include factories/seeders)
├── Providers/     # Service providers
routes/
├── web.php        # Web routes (Livewire classes)
└── console.php    # Artisan commands
database/
├── migrations/
├── seeders/
└── factories/
tests/
├── Feature/
└── Unit/
```

**Livewire Architecture:**
- Livewire components in `app/Livewire/`
- Complex components use `Actions/` class groups for business logic
- Use `WithFileUploads` trait for file uploads
- Upload to storage disks: `local`, `public`, `r2`, `b2`

**Page-Based Anonymous Livewire Components:**
- Most pages are anonymous Livewire classes defined **inline in Blade files** at `resources/views/pages/{feature}/⚡{name}.blade.php` (note the `⚡` prefix on the filename — required for the `pages::` view namespace to resolve).
- Routes map to these via the `pages::` namespace, e.g. `Route::livewire('chat', 'pages::chat.index')` renders `resources/views/pages/chat/⚡index.blade.php`.
- The Blade file begins with `<?php` + `use` statements, then `new #[Layout('layouts.app')] class extends Component { ... }`.
- There is **no** matching class under `app/Livewire/` for these — the class lives only in the Blade file. `app/Livewire/Actions/Logout.php` is the only conventional component class.
- When adding a new page: create the `⚡<name>.blade.php` file and register a `Route::livewire(...)` entry in `routes/web.php` (or `routes/settings.php`).

**Login OTP Flow (two-step auth after Fortify login):**
- `AppServiceProvider::register()` binds `LoginResponse` → `LoginOtpLoginResponse` and `RegisterResponse` → `LoginOtpRegisterResponse`, so a successful Fortify login redirects to `/auth/otp-challenge` instead of the dashboard.
- `login-otp` middleware alias (`App\Http\Middleware\EnsureLoginOtpVerified`) guards all authenticated routes — the session must be marked verified or the user is forced back to the challenge.
- `App\Services\LoginOtpService` issues/verifies/resends 6-digit codes (`LoginOtpChallenge` model); OTP delivery is dispatched as `App\Jobs\SendOtpJob` on the **`otp`** queue, sent via WhatsApp (priority) or email.
- Per-user channel preference lives in the `otp_channel_preference` column on `users`.

**AI Chatbot (`laravel/ai`):**
- `config/ai.php` defines provider drivers/keys; `config/ai-chat.php` defines the chat UI registry (labels + models per provider). Providers without an API key are auto-hidden by `App\Services\AiChat\ProviderRegistry`.
- `App\Ai\Agents\ChatAgent` implements `Laravel\Ai\Contracts\Agent` / `Conversational` (uses `Promptable` + `RemembersConversations`). Conversations persist via `laravel/ai`'s `agent_conversations` / `agent_conversation_messages` tables.
- Custom `9router` driver is registered in `AppServiceProvider::configureAiDrivers()` via `Ai::extend('9router', ...)`. `App\Ai\Providers\NineRouterProvider` extends `OpenRouterProvider` and swaps in `App\Ai\Gateways\NineRouterGateway`, which adds the `Accept: application/json` header the 9Router proxy needs to return JSON instead of SSE.

**Destructive DB guard:**
- `AppServiceProvider::configureDefaults()` calls `DB::prohibitDestructiveCommands(app()->isProduction())` — in production, `migrate:fresh`/`migrate:refresh`/`db:wipe` are blocked.

**Storage Configuration:**
- Filesystem disks defined in `config/filesystems.php`
- `s3` disk for Backblaze B2 (requires `league/flysystem-aws-s3-v3` package)
- `r2` disk for Cloudflare R2
- Environment variables in `.env`: `B2_*` for Backblaze B2, `R2_*` for Cloudflare R2
- `FILESYSTEM_DISK` selects default storage driver

**Test Structure:**
- PHPUnit tests in `tests/Feature/` and `tests/Unit/`
- Create tests with `php artisan make:test --phpunit {name}`
- Use factories in tests, never manual model creation in tinker
- After changes, run the minimum number of tests needed to verify

**Coding Conventions (Laravel Boost):**
- Use curly braces for control structures
- Constructor property promotion for dependencies
- Explicit return types and type hints
- TitleCase Enum keys
- PHPDoc blocks preferred over inline comments
- Reuse existing components before creating new ones
- Directory structure is fixed—don't create new base folders without approval

## Important Patterns

**When Creating Livewire Components:**
- Keep state server-side, validate in actions
- Use ` WithFileUploads` for file uploads
- Delete old files before uploading new ones
- Use `session()->flash('success/error', 'message')` for notifications

**When Using Storage:**
- Storage path in `B2_BUCKET.B2_ENDPOINT` format for B2
- Storage path in `https://{account_id}.r2.cloudflarestorage.com` format for R2
- Store files in structured subdirectories by type (images/, documents/, etc.)
- Access via `Storage::disk('b2')->url($path)` for public access
- Use `Storage::disk('b2')->temporaryUrl($path, $expiration)` for private access

**When Logging:**
- Always wrap in try-catch for storage operations
- Use `$this->fileName = time() . '_' . sanitize_filename($file->getClientOriginalName())` for unique names
- Log upload/delete events for tracking

**When Deleting Files:**
- Delete from storage before removing record: `$product->deleteAllFiles();`
- Use `Storage::disk('b2')->delete($path)` for single file
- Use `Storage::disk('b2')->delete(['path1', 'path2'])` for multiple

**Route Structure:**
- All web routes in `routes/web.php`
- Routes map directly to Livewire classes (no controllers for most cases)
- Named routes should be reused via `route('route.name')` instead of inline URLs

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
