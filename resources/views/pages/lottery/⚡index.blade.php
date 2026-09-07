<?php

use Flux\Flux;
use Illuminate\Support\Lottery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lottery')] #[Layout('layouts.app')] class extends Component {
    public bool $isSpinning = false;

    public ?string $resultEmoji = null;

    public ?string $resultTitle = null;

    public ?string $resultMessage = null;

    public ?string $resultTier = null;

    /** @var array<int, array{emoji: string, title: string, tier: string, time: string}> */
    public array $spinHistory = [];

    public int $totalSpins = 0;

    public int $totalWins = 0;

    public function mount(): void
    {
        $this->spinHistory = session('lottery_history', []);
        $this->totalSpins = session('lottery_total_spins', 0);
        $this->totalWins = session('lottery_total_wins', 0);
    }

    public function spin(): void
    {
        $this->isSpinning = true;
        $this->resultEmoji = null;
        $this->resultTitle = null;
        $this->resultMessage = null;
        $this->resultTier = null;

        $result = $this->runLottery();

        $this->resultEmoji = $result['emoji'];
        $this->resultTitle = $result['title'];
        $this->resultMessage = $result['message'];
        $this->resultTier = $result['tier'];

        $this->totalSpins++;

        if ($result['tier'] !== 'lose') {
            $this->totalWins++;
        }

        $historyEntry = [
            'emoji' => $result['emoji'],
            'title' => $result['title'],
            'tier' => $result['tier'],
            'time' => now()->format('H:i:s'),
        ];

        array_unshift($this->spinHistory, $historyEntry);

        if (count($this->spinHistory) > 8) {
            $this->spinHistory = array_slice($this->spinHistory, 0, 8);
        }

        session([
            'lottery_history' => $this->spinHistory,
            'lottery_total_spins' => $this->totalSpins,
            'lottery_total_wins' => $this->totalWins,
        ]);

        $this->isSpinning = false;

        if ($result['tier'] === 'jackpot') {
            Flux::toast(variant: 'success', text: '🎊 JACKPOT! ' . $result['title']);
        } elseif ($result['tier'] === 'gold') {
            Flux::toast(variant: 'success', text: '⭐ Selamat! ' . $result['title']);
        } elseif ($result['tier'] === 'silver') {
            Flux::toast(variant: 'success', text: '✨ Lumayan! ' . $result['title']);
        }

        $this->dispatch('spin-complete', emoji: $result['emoji']);
    }

    public function resetHistory(): void
    {
        $this->spinHistory = [];
        $this->totalSpins = 0;
        $this->totalWins = 0;
        $this->resultEmoji = null;
        $this->resultTitle = null;
        $this->resultMessage = null;
        $this->resultTier = null;

        session()->forget(['lottery_history', 'lottery_total_spins', 'lottery_total_wins']);

        Flux::toast(variant: 'success', text: 'Statistik dan riwayat berhasil direset.');
    }

    /** @return array{emoji: string, title: string, message: string, tier: string} */
    private function runLottery(): array
    {
        // Jackpot: 1 dari 50 (2%)
        $jackpot = Lottery::odds(1, 50)->winner(fn () => true)->loser(fn () => false)->choose();

        if ($jackpot) {
            return [
                'emoji' => '🎰',
                'title' => 'JACKPOT BESAR!',
                'message' => 'Luar biasa! Kamu mendapatkan hadiah utama yang sangat langka!',
                'tier' => 'jackpot',
            ];
        }

        // Gold: 1 dari 10 (10%)
        $gold = Lottery::odds(1, 10)->winner(fn () => true)->loser(fn () => false)->choose();

        if ($gold) {
            $goldPrizes = [
                ['emoji' => '🏆', 'title' => 'Hadiah Emas!', 'message' => 'Selamat! Kamu beruntung meraih hadiah emas hari ini!'],
                ['emoji' => '⭐', 'title' => 'Bintang Emas!', 'message' => 'Cahayamu bersinar terang! Hadiah emas untukmu!'],
                ['emoji' => '💎', 'title' => 'Berlian Emas!', 'message' => 'Kilau keberuntunganmu tak tertandingi!'],
            ];

            return array_merge($goldPrizes[array_rand($goldPrizes)], ['tier' => 'gold']);
        }

        // Silver: 1 dari 4 (25%)
        $silver = Lottery::odds(1, 4)->winner(fn () => true)->loser(fn () => false)->choose();

        if ($silver) {
            $silverPrizes = [
                ['emoji' => '🌟', 'title' => 'Hadiah Perak!', 'message' => 'Tidak buruk! Keberuntungan ada di pihakmu!'],
                ['emoji' => '🎁', 'title' => 'Kotak Kejutan!', 'message' => 'Senang bisa memberikanmu hadiah perak!'],
                ['emoji' => '🦄', 'title' => 'Unicorn Perak!', 'message' => 'Langka tapi tidak selangka jackpot, tetap keren!'],
            ];

            return array_merge($silverPrizes[array_rand($silverPrizes)], ['tier' => 'silver']);
        }

        // Bronze: 1 dari 2 (50%) dari sisa
        $bronze = Lottery::odds(1, 2)->winner(fn () => true)->loser(fn () => false)->choose();

        if ($bronze) {
            $bronzePrizes = [
                ['emoji' => '🍀', 'title' => 'Semanggi Beruntung!', 'message' => 'Sedikit keberuntungan untukmu hari ini!'],
                ['emoji' => '🌈', 'title' => 'Hadiah Pelangi!', 'message' => 'Indah! Setidaknya ada warna dalam hidupmu!'],
                ['emoji' => '🎈', 'title' => 'Balon Keberuntungan!', 'message' => 'Kecil tapi tetap membawa senyum!'],
            ];

            return array_merge($bronzePrizes[array_rand($bronzePrizes)], ['tier' => 'bronze']);
        }

        // Lose
        $losePhrases = [
            ['emoji' => '😅', 'title' => 'Belum Beruntung', 'message' => 'Coba lagi! Keberuntungan pasti akan datang padamu!'],
            ['emoji' => '🎭', 'title' => 'Nasib Belum Berpihak', 'message' => 'Jangan menyerah! Pemenang sejati tidak mudah putus asa!'],
            ['emoji' => '🌊', 'title' => 'Gelombang Belum Tepat', 'message' => 'Tunggu gelombang yang tepat, kamu pasti bisa!'],
            ['emoji' => '🦋', 'title' => 'Belum Waktunya', 'message' => 'Keberuntungan seperti kupu-kupu — datang saat waktunya!'],
        ];

        return array_merge($losePhrases[array_rand($losePhrases)], ['tier' => 'lose']);
    }

    public function winRate(): int
    {
        if ($this->totalSpins === 0) {
            return 0;
        }

        return (int) round(($this->totalWins / $this->totalSpins) * 100);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8 px-2 py-4 lg:px-4">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('🎰 Undian Beruntung') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Putar mesin slot dan uji keberuntunganmu! Ditenagai oleh Laravel Lottery.') }}</flux:text>
    </div>

    {{-- Undian Beruntung --}}
    <div
        x-data="{
            spinning: false,
            showResult: false,
            slotSymbols: ['🎰','🍒','🍋','🍇','🔔','⭐','💎','🎁','🌟','🎪'],
            slots: ['🎰', '🎰', '🎰'],
            spinInterval: null,
            tick: 0,

            startSpin() {
                if (this.spinning) return;
                this.spinning = true;
                this.showResult = false;
                this.tick = 0;

                this.spinInterval = setInterval(() => {
                    this.tick++;
                    this.slots = this.slots.map(() =>
                        this.slotSymbols[Math.floor(Math.random() * this.slotSymbols.length)]
                    );

                    if (this.tick >= 18) {
                        clearInterval(this.spinInterval);
                        this.spinning = false;
                    }
                }, 80);
            },

            stopAndReveal(emoji) {
                clearInterval(this.spinInterval);
                this.spinning = false;
                this.slots = [emoji, emoji, emoji];
                this.showResult = true;
            }
        }"
        x-on:spin-complete.window="stopAndReveal($event.detail.emoji)"
        class="grid gap-6 lg:grid-cols-[1fr_320px]"
    >
        {{-- Left: Game Area --}}
        <div class="flex flex-col items-center gap-6">

            {{-- Stats --}}
            @if ($totalSpins > 0)
                <div class="flex w-full max-w-md justify-center gap-4">
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-2 text-center dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $totalSpins }}</div>
                        <div class="text-xs text-violet-600 dark:text-violet-400">Total Putar</div>
                    </div>
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-2 text-center dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $totalWins }}</div>
                        <div class="text-xs text-violet-600 dark:text-violet-400">Menang</div>
                    </div>
                    <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-2 text-center dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $this->winRate() }}%</div>
                        <div class="text-xs text-violet-600 dark:text-violet-400">Win Rate</div>
                    </div>
                </div>
            @endif

            {{-- Slot Display --}}
            <div class="w-full max-w-md">
                <div class="relative rounded-2xl border-4 border-yellow-400 bg-gradient-to-b from-yellow-50 to-amber-50 p-6 shadow-lg dark:border-yellow-500 dark:from-zinc-800 dark:to-zinc-900">
                    {{-- Decorative lights --}}
                    <div class="absolute -top-2 left-0 right-0 flex justify-around px-6">
                        <div class="h-3 w-3 rounded-full bg-red-400 shadow-sm" :class="spinning ? 'animate-pulse' : ''"></div>
                        <div class="h-3 w-3 rounded-full bg-yellow-400 shadow-sm" :class="spinning ? 'animate-pulse [animation-delay:150ms]' : ''"></div>
                        <div class="h-3 w-3 rounded-full bg-green-400 shadow-sm" :class="spinning ? 'animate-pulse [animation-delay:300ms]' : ''"></div>
                        <div class="h-3 w-3 rounded-full bg-blue-400 shadow-sm" :class="spinning ? 'animate-pulse [animation-delay:450ms]' : ''"></div>
                        <div class="h-3 w-3 rounded-full bg-purple-400 shadow-sm" :class="spinning ? 'animate-pulse [animation-delay:600ms]' : ''"></div>
                    </div>

                    {{-- Slots --}}
                    <div class="flex items-center justify-center gap-3">
                        <template x-for="(sym, i) in slots" :key="i">
                            <div
                                class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-xl border-2 border-yellow-300 bg-white shadow-inner transition-all duration-100 dark:border-yellow-600 dark:bg-zinc-700"
                                :class="spinning ? 'scale-95' : 'scale-100'"
                            >
                                <span
                                    class="select-none text-5xl transition-all"
                                    :class="spinning ? 'blur-sm scale-110' : 'blur-0 scale-100'"
                                    x-text="sym"
                                ></span>
                            </div>
                        </template>
                    </div>

                    {{-- Win line indicator --}}
                    <div class="mt-3 flex items-center justify-center gap-2">
                        <div class="h-0.5 flex-1 bg-gradient-to-r from-transparent via-yellow-400 to-transparent"></div>
                        <span class="text-xs font-semibold text-yellow-600 dark:text-yellow-400">WIN LINE</span>
                        <div class="h-0.5 flex-1 bg-gradient-to-r from-transparent via-yellow-400 to-transparent"></div>
                    </div>
                </div>
            </div>

            {{-- Result Card --}}
            @if ($resultEmoji && $resultTitle && $resultTier)
                <div
                    x-show="showResult"
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    class="w-full max-w-md overflow-hidden rounded-2xl border shadow-md"
                    :class="{
                        'border-yellow-400 bg-gradient-to-br from-yellow-50 to-amber-100 dark:border-yellow-600 dark:from-yellow-950/40 dark:to-amber-950/30': '{{ $resultTier }}' === 'jackpot',
                        'border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50 dark:border-amber-700 dark:from-amber-950/30 dark:to-orange-950/20': '{{ $resultTier }}' === 'gold',
                        'border-slate-300 bg-gradient-to-br from-slate-50 to-zinc-100 dark:border-slate-600 dark:from-slate-950/50 dark:to-zinc-950/30': '{{ $resultTier }}' === 'silver',
                        'border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 dark:border-orange-800 dark:from-orange-950/30 dark:to-amber-950/20': '{{ $resultTier }}' === 'bronze',
                        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50': '{{ $resultTier }}' === 'lose',
                    }"
                >
                    <div class="p-5 text-center">
                        @if ($resultTier === 'jackpot')
                            <div class="mb-2 text-xs font-bold uppercase tracking-widest text-yellow-600 dark:text-yellow-400">
                                ✨ HADIAH UTAMA ✨
                            </div>
                        @elseif ($resultTier === 'gold')
                            <div class="mb-2 text-xs font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                                ⭐ KELAS EMAS
                            </div>
                        @elseif ($resultTier === 'silver')
                            <div class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                🥈 KELAS PERAK
                            </div>
                        @elseif ($resultTier === 'bronze')
                            <div class="mb-2 text-xs font-bold uppercase tracking-widest text-orange-500 dark:text-orange-400">
                                🥉 KELAS PERUNGGU
                            </div>
                        @endif

                        <div
                            class="mb-3 text-6xl"
                            :class="'{{ $resultTier }}' === 'jackpot' ? 'animate-bounce' : ''"
                        >{{ $resultEmoji }}</div>

                        <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $resultTitle }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $resultMessage }}
                        </div>

                        {{-- Probability info --}}
                        @if ($resultTier !== 'lose')
                            <div class="mt-3 inline-block rounded-full bg-white/70 px-3 py-1 text-xs text-zinc-500 dark:bg-zinc-800/70 dark:text-zinc-400">
                                @if ($resultTier === 'jackpot') Peluang: 2% (1 dari 50)
                                @elseif ($resultTier === 'gold') Peluang: ~10%
                                @elseif ($resultTier === 'silver') Peluang: ~22%
                                @elseif ($resultTier === 'bronze') Peluang: ~33%
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Spin Button --}}
            <div wire:loading.remove wire:target="spin">
                <button
                    type="button"
                    x-on:click="startSpin(); $wire.spin()"
                    :disabled="spinning"
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-600 to-purple-600 px-10 py-4 text-lg font-bold text-white shadow-lg transition-all duration-200 hover:-translate-y-1 hover:shadow-xl active:translate-y-0 disabled:opacity-60 disabled:cursor-not-allowed dark:from-violet-700 dark:to-purple-700"
                >
                    <span class="relative z-10 flex items-center gap-2" x-show="!spinning">
                        🎰 Putar Sekarang!
                    </span>
                    <span class="relative z-10 flex items-center gap-2" x-show="spinning">
                        <svg class="size-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Memutar...
                    </span>
                    <div class="absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
                </button>
            </div>

            <div wire:loading wire:target="spin" class="flex items-center gap-2 text-sm text-zinc-500">
                <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Menghitung keberuntunganmu...
            </div>

        </div>

        {{-- Right: Info + History --}}
        <div class="flex flex-col gap-4">

            {{-- Probability Table --}}
            <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-3 text-sm font-bold text-zinc-700 dark:text-zinc-200">🎲 Tabel Peluang</div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-yellow-600 dark:text-yellow-400">🎰 Jackpot</span>
                        <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400">2%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-amber-600 dark:text-amber-400">🏆 Gold</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">~10%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">🌟 Silver</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400">~22%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-orange-500 dark:text-orange-400">🍀 Bronze</span>
                        <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-600 dark:bg-orange-900/40 dark:text-orange-400">~33%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400 dark:text-zinc-500">😅 Lose</span>
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">~33%</span>
                    </div>
                </div>
            </div>

            {{-- History --}}
            <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-bold text-zinc-700 dark:text-zinc-200">📜 Riwayat</div>
                    @if (count($spinHistory) > 0)
                        <button
                            type="button"
                            wire:click="resetHistory"
                            class="text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                        >
                            Reset
                        </button>
                    @endif
                </div>

                @if (count($spinHistory) > 0)
                    <div class="space-y-1.5">
                        @foreach ($spinHistory as $entry)
                            <div class="flex items-center gap-2 rounded-lg bg-white/80 px-3 py-1.5 text-sm dark:bg-zinc-900/60">
                                <span class="text-lg">{{ $entry['emoji'] }}</span>
                                <span class="flex-1 truncate text-zinc-700 dark:text-zinc-300">{{ $entry['title'] }}</span>
                                <span class="text-xs text-zinc-400">{{ $entry['time'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-zinc-200 p-4 text-center dark:border-zinc-700">
                        <div class="text-3xl">🎰</div>
                        <div class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">Belum ada riwayat</div>
                        <div class="text-xs text-zinc-400 dark:text-zinc-500">Putar untuk mulai!</div>
                    </div>
                @endif

                {{-- How it works --}}
                <div class="mt-4 rounded-xl border border-violet-100 bg-violet-50 p-4 dark:border-violet-900/30 dark:bg-violet-950/20">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400">
                        Cara Kerja
                    </div>
                    <div class="space-y-1 text-xs text-violet-700 dark:text-violet-300">
                        <div>1. Klik tombol <strong>Putar</strong></div>
                        <div>2. Laravel <code class="rounded bg-violet-100 px-1 dark:bg-violet-900/40">Lottery::odds()</code> menentukan hasilmu</div>
                        <div>3. Animasi slot berhenti sesuai hasil server</div>
                        <div>4. Kumpulkan kemenangan sebanyak mungkin! 🎊</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
