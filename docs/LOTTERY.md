# Lottery Mini‑Game Documentation

## Overview
The **Lottery** feature is a lightweight slot‑machine style game built with:
- **Laravel Lottery** (`Illuminate\Support\Lottery`) for probabilistic outcomes.
- **Livewire 4** component (`pages::lottery.index`) to handle server‑side state, session persistence, and toast notifications.
- **Alpine.js** for smooth client‑side spinning animation and real‑time UI updates.
- **Flux UI** icons and TailwindCSS 4 for styling.

It lives at the route `{{ route('lottery.index') }}` and is accessible via the **Lottery** item in the sidebar.

## How to Play
1. Click the **"Putar Sekarang!"** button.
2. The slot icons spin for ~1.4 seconds (18 ticks, 80 ms interval).
3. The server runs the lottery logic and returns an emoji, title, message and tier.
4. The animation stops on the winning emoji and a result card is shown.
5. Statistics (total spins, wins, win‑rate) and a history of the last 8 spins are displayed on the right.

## Odds & Tiers
| Tier | Odds | Description |
|------|------|-------------|
| **Jackpot** | 2 % (1 / 50) | 🎰 **JACKPOT BESAR!** – rare highest prize |
| **Gold** | ~10 % (1 / 10) | 🏆, ⭐, 💎 – premium prizes |
| **Silver** | ~22 % (1 / 4) | 🌟, 🎁, 🦄 – solid wins |
| **Bronze** | ~33 % (1 / 2 of remaining) | 🍀, 🌈, 🎈 – modest rewards |
| **Lose** | ~33 % | 😅, 🎭, 🌊, 🦋 – try again |

These probabilities are defined in `runLottery()` using `Lottery::odds()` calls.

## Technical Details
- **Component class** (`resources/views/pages/lottery/⚡index.blade.php`):
  - Holds state: `$isSpinning`, `$resultEmoji`, `$resultTitle`, `$resultMessage`, `$resultTier`, `$spinHistory`, `$totalSpins`, `$totalWins`.
  - `mount()` loads session data.
  - `spin()` runs the lottery, updates stats, persists to session, dispatches a browser event `spin‑complete` and shows toasts via `Flux::toast()`.
  - `resetHistory()` clears stats and session.
- **Blade view** contains the Alpine `x-data` object that:
  - Manages the spinning animation (`spinning`, `slots`, `spinInterval`).
  - Listens for `x‑on:spin‑complete.window` to reveal the final emoji.
- **Routing** (`routes/web.php`): `Route::livewire('lottery', 'pages::lottery.index')->name('lottery.index');`
- **Sidebar navigation** added in `resources/views/layouts/app/sidebar.blade.php` with a ticket icon.

## Testing
Four feature tests are provided (`tests/Feature/LotteryTest.php`):
- Guest redirect to login.
- Authenticated access returns 200.
- Presence of the spin button (`Putar Sekarang`).
- Presence of the probability table (`Tabel Peluang`).

Run them with:
```bash
php artisan test --compact tests/Feature/LotteryTest.php
```

## Future Improvements
- Add configurable odds via admin UI.
- Persist full spin history beyond the last 8 entries.
- Add social sharing of win results.

---
*Documentation generated on $(date '+%Y-%m-%d')*