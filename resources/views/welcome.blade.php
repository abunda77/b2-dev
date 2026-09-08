<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} — the prototype library</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fonts

        <style>
            html,
            body {
                overflow-x: clip;
            }

            .display-heading {
                font-family: var(--font-display);
                font-weight: 400;
                letter-spacing: -0.015em;
                line-height: 1.05;
                overflow-wrap: anywhere;
                min-width: 0;
            }

            @media (pointer: fine) {
                .card-link:hover .card-arrow {
                    transform: translateX(0.25rem);
                }
            }

            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    scroll-behavior: auto !important;
                    transition-duration: 0.01ms !important;
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                }
            }
        </style>
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <header class="bg-paper">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 pt-6 sm:px-8">
                <p class="truncate text-[0.6875rem] font-medium uppercase tracking-[0.2em] text-muted">b2-dev · a living catalogue</p>
                <p class="whitespace-nowrap text-[0.6875rem] font-medium uppercase tracking-[0.2em] text-muted">{{ now()->format('j F Y') }}</p>
            </div>

            <div class="mx-auto w-full max-w-6xl px-5 pb-7 sm:px-8">
                <a href="{{ route('home') }}" class="mx-auto block w-fit text-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marker" aria-label="Prototype library — home">
                    <h1 class="display-heading text-[clamp(3rem,8vw,5rem)] text-ink">B2</h1>
                    <span class="mt-1 block text-[0.6875rem] font-semibold uppercase tracking-[0.34em] text-muted">The prototype library</span>
                </a>

                <nav aria-label="Primary" class="mt-7 flex flex-wrap items-center justify-center gap-x-1 gap-y-2">
                    <a href="#library" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Library</a>
                    @if (Route::has('docs.index'))
                        <a href="{{ route('docs.index') }}" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Docs</a>
                    @endif
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('dashboard') }}" class="ml-2 inline-flex min-h-10 items-center whitespace-nowrap rounded-[var(--radius-pap)] bg-ink px-4 py-2 text-sm font-semibold text-paper transition-[transform,background-color] duration-200 hover:-translate-y-0.5 hover:bg-marker focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Open dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Log in</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="ml-2 inline-flex min-h-10 items-center whitespace-nowrap rounded-[var(--radius-pap)] bg-ink px-4 py-2 text-sm font-semibold text-paper transition-[transform,background-color] duration-200 hover:-translate-y-0.5 hover:bg-marker focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Get started</a>
                            @endif
                        @endauth
                    @endif
                </nav>
            </div>

            <div class="h-[7px] border-y border-(--color-rule)" aria-hidden="true"></div>
        </header>

        <main class="flex-1">
            <section class="mx-auto w-full max-w-6xl px-5 pt-16 pb-10 sm:px-8 sm:pt-24 sm:pb-14">
                <div class="grid gap-8 lg:grid-cols-[minmax(0,0.7fr)_minmax(0,1fr)] lg:items-end lg:gap-16">
                    <div class="max-w-xl">
                        <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.24em] text-muted">The collection</p>
                        <h2 class="display-heading mt-4 text-[clamp(2rem,4.5vw,3.25rem)] text-balance text-ink">Eleven small tools, one workspace.</h2>
                    </div>
                    <p class="max-w-xl text-base leading-7 text-ash lg:justify-self-end">A focused shelf of Laravel prototypes for everyday work — manage people, move messages, generate documents, and test what comes next. Each module is deliberately small and connected to the same authenticated workspace.</p>
                </div>
            </section>

            <section id="library" class="mx-auto w-full max-w-6xl scroll-mt-8 px-5 pb-16 sm:px-8 sm:pb-24">
                <div class="grid min-w-0 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $modules = [
                            ['route' => 'chat.index', 'icon' => 'sparkles', 'title' => 'Chat AI', 'description' => 'Explore conversations with your configured AI providers.'],
                            ['route' => 'warga.index', 'icon' => 'users', 'title' => 'Warga', 'description' => 'Keep people, profiles, and supporting files in one place.'],
                            ['route' => 'notes.index', 'icon' => 'document-text', 'title' => 'Notes', 'description' => 'Capture short ideas and useful context without friction.'],
                            ['route' => 'whatsapp.send-message', 'icon' => 'chat-bubble-left', 'title' => 'WhatsApp', 'description' => 'Compose and send messages through the configured gateway.'],
                            ['route' => 'email.send-message', 'icon' => 'envelope', 'title' => 'Email', 'description' => 'Prepare and deliver email messages from the workspace.'],
                            ['route' => 'qr-code.generate', 'icon' => 'qr-code', 'title' => 'QR Code', 'description' => 'Generate a downloadable QR code from a simple input.'],
                            ['route' => 'faktur.generate', 'icon' => 'document-text', 'title' => 'Faktur', 'description' => 'Build a printable invoice and export it as a PDF.'],
                            ['route' => 'docs.index', 'icon' => 'book-open', 'title' => 'Docs', 'description' => 'Browse project notes and practical implementation guides.'],
                            ['route' => 'lottery.index', 'icon' => 'ticket', 'title' => 'Lottery', 'description' => 'Run a lightweight drawing flow for names or entries.'],
                            ['route' => 'gallery.index', 'icon' => 'photo', 'title' => 'Gallery', 'description' => 'Review uploaded images in a simple visual collection.'],
                            ['route' => 'file-host.index', 'icon' => 'folder', 'title' => 'File host', 'description' => 'Hand files a permanent place with friendly, direct URLs.'],
                        ];
                    @endphp

                    @foreach ($modules as $i => $module)
                        <a href="{{ route($module['route']) }}" class="card-link group flex min-w-0 flex-col border-b border-r border-(--color-rule) bg-paper p-6 transition-[transform,background-color] duration-200 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-marker pointer-fine:hover:-translate-y-0.5 pointer-fine:hover:bg-paper-2 sm:p-8">
                            <div class="flex items-center justify-between gap-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-pap)] bg-paper-2 text-ink">
                                    <flux:icon name="{{ $module['icon'] }}" variant="solid" class="size-5" />
                                </span>
                                <span class="whitespace-nowrap text-xs font-medium tracking-[0.2em] text-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <h3 class="mt-10 text-lg font-semibold tracking-tight text-ink">{{ __($module['title']) }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-6 text-ash">{{ __($module['description']) }}</p>
                            <span class="mt-6 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-muted">Open module <span class="card-arrow inline-block transition-transform duration-200" aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-(--color-rule)">
                <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-5 py-16 sm:px-8 sm:py-20 lg:flex-row lg:items-end lg:justify-between lg:gap-12">
                    <div class="max-w-2xl">
                        <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.24em] text-muted">From the bench</p>
                        <h2 class="display-heading mt-4 text-[clamp(1.75rem,3.5vw,2.5rem)] text-balance text-ink">The library is a living surface.</h2>
                        <p class="mt-4 max-w-xl text-base leading-7 text-ash">Start with a module, follow the path that feels useful, and return to the dashboard when you are ready to switch gears.</p>
                    </div>
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-1.5 whitespace-nowrap rounded-sm text-sm font-semibold text-marker hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marker">Open your workspace <span class="inline-block transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true">→</span></a>
                        @else
                            <a href="{{ route('login') }}" class="group inline-flex items-center gap-1.5 whitespace-nowrap rounded-sm text-sm font-semibold text-marker hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marker">Sign in to explore <span class="inline-block transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true">→</span></a>
                        @endauth
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-(--color-rule)">
            <div class="mx-auto w-full max-w-6xl px-5 py-12 sm:px-8">
                <div class="grid gap-8 lg:grid-cols-[minmax(0,0.6fr)_minmax(0,1fr)] lg:items-end">
                    <div class="max-w-xs">
                        <p class="text-2xl font-medium tracking-tight text-ink">B2</p>
                        <p class="mt-1 text-sm text-muted">Small tools, ready for the next idea.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-1 gap-y-1 lg:justify-self-end">
                        <a href="#library" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Library</a>
                        @if (Route::has('docs.index'))
                            <a href="{{ route('docs.index') }}" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Docs</a>
                        @endif
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="whitespace-nowrap rounded-sm px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marker">Log in</a>
                            @endauth
                        @endif
                    </div>
                </div>
                <div class="mt-10 flex flex-wrap items-center justify-between gap-2 border-t border-(--color-rule) pt-5">
                    <p class="text-xs text-ash">© {{ date('Y') }} {{ config('app.name', 'Laravel') }} · the prototype library</p>
                    <p class="text-xs text-ash">Laravel 13 · Livewire · Flux</p>
                </div>
            </div>
        </footer>
    </body>
</html>