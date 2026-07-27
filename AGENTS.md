# AGENTS.md

## Commands

```bash
# Setup (first run)
composer run setup

# Dev server (PHP + Vite concurrently)
composer run dev

# Build assets
npm run build

# Code quality (run after every PHP edit)
vendor/bin/pint --dirty --format agent

# Lint check (dry run)
composer run lint:check

# Static analysis (PHPStan level 7)
composer run types:check

# Tests (PHPUnit)
php artisan test --compact                                          # all tests
php artisan test --compact tests/Feature/ExampleTest.php            # one file
php artisan test --compact --filter=testName                        # one test
php artisan make:test --phpunit {name}                              # create PHPUnit test

# Full CI gate (config:clear -> lint:check -> types:check -> test)
composer run test

# Queue worker (required for OTP & async email delivery)
php artisan queue:work --queue=otp,default
```

**PHPUnit env** (`phpunit.xml`): SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `MAIL_MAILER=array`. No external services needed for tests.

**Vite manifest error**: run `npm run build` or `composer run dev`.

## Architecture & Framework Quirks

**Stack**: Laravel 13 · PHP 8.3 · Livewire 4 · Flux UI v2 · Tailwind v4 · Fortify v1 · laravel/ai v0 · PHPUnit 12 · Larastan v3 (level 7)

**Database**: SQLite (dev) · PostgreSQL/MySQL (prod) · `DB::prohibitDestructiveCommands()` in production

### Page-Based Anonymous Livewire (critical)

Most pages are **anonymous Livewire classes defined inline inside Blade files**:

- **File location**: `resources/views/pages/{feature}/⚡{name}.blade.php` — the `⚡` prefix on the filename is **required** for `pages::` namespace resolution.
- **Route definition**: `Route::livewire('path', 'pages::feature.name')` in `routes/web.php` or `routes/settings.php`.
- **Inline class**: `<?php` + imports, followed by `new #[Layout('layouts.app')] class extends Component { ... }`.
- Do **not** create matching class files in `app/Livewire/`. `app/Livewire/Actions/Logout.php` is the **only** conventional Livewire class in the app.

### Routing

- `routes/web.php` — main web routes (includes `routes/settings.php`).
- `routes/settings.php` — profile, appearance, security, and `.well-known/passkey-endpoints`.
- `routes/console.php` — scheduled Artisan tasks (e.g. daily `livewire:clear-tmp`).
- **No `api.php`** file — `api/*` requests return JSON exceptions via `bootstrap/app.php`.
- Middleware alias `login-otp` → `App\Http\Middleware\EnsureLoginOtpVerified`.

### Login OTP Flow

Two-step auth after Fortify password login:
- `AppServiceProvider::register()` overrides `LoginResponse` → `LoginOtpLoginResponse`, redirecting logins to `/auth/otp-challenge`.
- `login-otp` middleware guards authenticated routes until OTP is verified.
- `SendOtpJob` dispatches to the **`otp`** queue; delivery uses WhatsApp (priority) or email based on `users.otp_channel_preference`.

### AI Chatbot (`laravel/ai`)

- Custom `9router` driver registered in `AppServiceProvider::configureAiDrivers()` via `Ai::extend('9router', ...)`.
- `App\Ai\Providers\NineRouterProvider` extends `OpenRouterProvider` and uses `NineRouterGateway` to force `Accept: application/json`.
- `App\Ai\Agents\ChatAgent` implements `Agent` + `Conversational` with `RemembersConversations` (persisted in `agent_conversations` table).
- Provider availability managed via `config/ai-chat.php` and `App\Services\AiChat\ProviderRegistry`.

### Storage & Disks

- `s3` disk: Backblaze B2 (`B2_*` env vars, requires `league/flysystem-aws-s3-v3`). PDF invoices stored here via `FakturPdfService`.
- `r2` disk: Cloudflare R2 (`R2_*` env vars).
- `local` disk: temporary files (QR code PNG/JPG in `qr-codes-tmp/`, cleaned daily by `livewire:clear-tmp`).

### Key Services

| Service | Location | Purpose |
|---------|----------|---------|
| `LoginOtpService` | `app/Services/LoginOtpService.php` | Issue, verify, and resend OTP codes |
| `FakturPdfService` | `app/Services/FakturPdfService.php` | Invoice PDF generation (dompdf) & B2 storage |
| `QrCodeTemporaryFileService` | `app/Services/QrCodeTemporaryFileService.php` | Temp QR code PNG/JPG generation & cleanup |
| `MarkdownRendererService` | `app/Services/MarkdownRendererService.php` | GFM rendering for docs viewer |
| `ProviderRegistry` | `app/Services/AiChat/ProviderRegistry.php` | AI provider visibility & configuration |

## Conventions

- **Formatter**: Always run `vendor/bin/pint --dirty --format agent` after editing PHP files.
- **Tests**: Write PHPUnit classes (not Pest). Every code change requires a unit/feature test. Use `php artisan make:test --phpunit {name}`.
- **No Documentation Files**: Never create `.md` or documentation files unless explicitly requested by the user.
- **Artisan Generator**: Pass `--no-interaction` to `php artisan make:` commands.
- **UI Components**: Reuse existing `<flux:*>` components and established patterns. Do not create new base directories without approval.
