# AGENTS.md

## Commands

```bash
# Setup (first run)
composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate

# Dev server (PHP + Vite concurrently)
composer run dev

# Build assets
npm run build

# Code quality (run after every PHP edit)
vendor/bin/pint --dirty --format agent

# Static analysis
php artisan types:check

# Tests
php artisan test --compact                                          # all tests
php artisan test --compact tests/Feature/ExampleTest.php            # one file
php artisan test --compact --filter=testName                        # one test
php artisan make:test --phpunit {name}                              # create test

# Full CI gate (clear config → lint → types → test)
composer run test

# Queue worker (required for OTP & email)
php artisan queue:work --queue=otp,default
```

**PHPUnit env** (`phpunit.xml`): SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `MAIL_MAILER=array`. No external services needed for tests.

**Vite manifest error**: run `npm run build` or `composer run dev`.

## Architecture

**Stack**: Laravel 13 · PHP 8.3 · Livewire 4 · Flux UI v2 · Tailwind v4 · Fortify v1 · laravel/ai v0 · PHPUnit 12 · Larastan v3 (level 7)

**Database**: SQLite (dev) · PostgreSQL/MySQL (prod) · `DB::prohibitDestructiveCommands()` in production

### Page-Based Anonymous Livewire (critical)

Most pages are **anonymous Livewire classes defined inline in Blade files**:

- File path: `resources/views/pages/{feature}/⚡{name}.blade.php` — the `⚡` prefix is **required** for `pages::` namespace resolution.
- Route: `Route::livewire('path', 'pages::feature.name')` in `routes/web.php` or `routes/settings.php`.
- No matching class in `app/Livewire/` — the class lives only in the Blade file.
- File structure: `<?php` + use statements, then `new #[Layout('layouts.app')] class extends Component { ... }`.
- `app/Livewire/Actions/Logout.php` is the **only** conventional Livewire class.

**To add a new page**: create `resources/views/pages/{feature}/⚡{name}.blade.php`, register `Route::livewire(...)` in `routes/web.php`.

### Routing

- `routes/web.php` — all web routes (Livewire pages)
- `routes/settings.php` — `require`d from `web.php`; settings routes live here
- `routes/console.php` — scheduled `livewire:clear-tmp` daily
- **No `api.php`** by default — add via `withRouting(api: ...)` in `bootstrap/app.php` if needed
- Middleware alias `login-otp` → `App\Http\Middleware\EnsureLoginOtpVerified`
- `api/*` requests render JSON exceptions

### Login OTP Flow

Two-step auth after Fortify password login. `AppServiceProvider::register()` binds `LoginResponse` → `LoginOtpLoginResponse`, redirecting to `/auth/otp-challenge` instead of dashboard. `login-otp` middleware guards all authenticated routes. OTP delivery: `App\Jobs\SendOtpJob` on queue `otp`, via WhatsApp (priority) or email. Per-user channel preference in `users.otp_channel_preference`.

### AI Chatbot

- Custom `9router` driver registered in `AppServiceProvider::configureAiDrivers()` via `Ai::extend('9router', ...)`.
- `App\Ai\Providers\NineRouterProvider` extends `OpenRouterProvider` with custom gateway adding `Accept: application/json` header.
- `App\Ai\Agents\ChatAgent` implements `Agent` + `Conversational` with `RemembersConversations` (persists to `agent_conversations` tables).
- `config/ai-chat.php` defines chat UI registry (labels + models per provider).
- Providers without API key are auto-hidden by `App\Services\AiChat\ProviderRegistry`.

### Storage

- `s3` disk: Backblaze B2 (`B2_*` env vars, requires `league/flysystem-aws-s3-v3`)
- `r2` disk: Cloudflare R2 (`R2_*` env vars)
- `local` disk: temp files (QR codes in `qr-codes-tmp/`, cleaned by `livewire:clear-tmp` schedule)
- PDF invoices stored on `b2` disk via `FakturPdfService` (dompdf)
- `temporaryUrl()` for private access, `url()` for public

### Key Services

| Service | Location | Purpose |
|---------|----------|---------|
| `LoginOtpService` | `app/Services/LoginOtpService.php` | Issue/verify/resend OTP codes |
| `FakturPdfService` | `app/Services/FakturPdfService.php` | PDF invoice generation + B2 upload |
| `QrCodeTemporaryFileService` | `app/Services/QrCodeTemporaryFileService.php` | QR code PNG/JPG generation + cleanup |
| `MarkdownRendererService` | `app/Services/MarkdownRendererService.php` | GFM rendering for docs viewer |
| `ProviderRegistry` | `app/Services/AiChat/ProviderRegistry.php` | AI provider visibility control |

### Feature Pages

| Feature | Route | Page File |
|---------|-------|-----------|
| Chat | `/chat` | `pages/chat/⚡index.blade.php` |
| Warga | `/warga` | `pages/warga/⚡index.blade.php` |
| Notes | `/notes` | `pages/notes/⚡index.blade.php` |
| WhatsApp | `/whatsapp/send-message` | `pages/whatsapp/⚡send-message.blade.php` |
| Email | `/email/send-message` | `pages/email/⚡send-message.blade.php` |
| QR Code | `/qr-code/generate` | `pages/qr-code/⚡generate.blade.php` |
| Faktur | `/faktur/generate` | `pages/faktur/⚡generate.blade.php` |
| Docs | `/docs` | `pages/docs/⚡index.blade.php` |

### Vite

- Entry points: `resources/css/app.css`, `resources/js/app.js`, `resources/js/passkeys.js`
- Tailwind v4 via `@tailwindcss/vite` plugin (no `tailwind.config.js`)
- `@tailwindcss/typography` for prose styling
- Fonts: Bunny Fonts (Instrument Sans)
- Dev server CORS enabled

### Observability

- `binarybuilds/laritor-client` for APM — config via `LARITOR_*` env vars
- `laravel/octane` installed — server configured via `OCTANE_SERVER` env
- `laravel/chisel` installed

### AI SDK Stubs

`stubs/` contains AI SDK templates: `agent.stub`, `structured-agent.stub`, `tool.stub`, `agent-middleware.stub`.

## Conventions

- **Never create docs** unless explicitly requested.
- **Always run `vendor/bin/pint --dirty --format agent`** after editing PHP files.
- **Always write tests** (PHPUnit classes, not Pest). Every change needs a test.
- **Use `php artisan make:`** commands with `--no-interaction` for generating files.
- **Reuse existing components** before creating new ones. Check sibling files for patterns.
- **No new base folders** without approval.
- **No dependency changes** without approval.
- **PHPDoc blocks** preferred over inline comments. No comments unless asked.
- **PHP 8 constructor promotion** and explicit return types everywhere.
- **Named routes** via `route()` for all URL generation.
