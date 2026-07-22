# Panduan Laravel Reverb (WebSocket Server First-Party)

## Apa itu Laravel Reverb?

**Laravel Reverb** adalah WebSocket server first-party resmi Laravel yang dirilis bersama Laravel 11. Package ini menggantikan kebutuhan akan Pusher, Ably, atau Laravel Echo Server (Node.js) dengan menyediakan WebSocket server native PHP yang performa tinggi, scalable, dan terintegrasi penuh dengan ekosistem Laravel.

**Package:** `laravel/reverb` (terpisah dari `laravel/framework`)

**Repositori:** https://github.com/laravel/reverb

---

## Kenapa Memilih Reverb?

| Fitur | Laravel Reverb | Pusher | Laravel Echo Server (Node) |
|-------|---------------|--------|---------------------------|
| **Hosting** | Self-hosted (PHP) | SaaS (cloud) | Self-hosted (Node.js) |
| **Biaya** | Gratis (infrastructure Anda) | Berbayar per connection/msg | Gratis (infrastructure Node.js) |
| **Latency** | Sangat rendah (PHP persistent) | Rendah (edge network) | Rendah |
| **Scaling** | Horizontal (Octane/RoadRunner) | Otomatis (managed) | Horizontal (PM2/K8s) |
| **Integrasi Laravel** | Native (Broadcasting, Echo) | Via `pusher-php-server` | Via `laravel-echo-server` |
| **Debugging** | Laravel Telescope, Logs | Dashboard Pusher | Logs Node.js |
| **SSL/HTTPS** | Via reverse proxy (Nginx/Caddy) | Otomatis | Via reverse proxy |

---

## Instalasi

### 1. Install Package

```bash
composer require laravel/reverb
```

### 2. Publish Config & Assets

```bash
php artisan reverb:install
```

Perintah ini akan:
- Publish `config/reverb.php`
- Publish `resources/js/echo.js` (Laravel Echo client setup)
- Update `config/broadcasting.php` dengan driver `reverb`
- Update `.env` dengan variabel Reverb

### 3. Environment Variables (`.env`)

```env
# Reverb Server
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Broadcasting (Laravel Echo client side)
BROADCAST_DRIVER=reverb
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_APP_ID="${REVERB_APP_ID}"

# App Key Reverb (generate via: php artisan reverb:install atau manual)
REVERB_APP_KEY=your-app-key
REVERB_APP_ID=your-app-id
REVERB_SECRET=your-secret
```

> **Tip:** Gunakan `php artisan reverb:install` untuk generate `REVERB_APP_KEY` dan `REVERB_APP_ID` otomatis.

---

## Menjalankan Server Reverb

### Development

```bash
# Terminal terpisah dari php artisan serve
php artisan reverb:start

# Dengan debug/verbose
php artisan reverb:start --debug

# Host & port custom
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Production (dengan Laravel Octane / RoadRunner / FrankenPHP)

**Direkomendasikan:** Jalankan Reverb di atas **Laravel Octane** (Swoole / RoadRunner / FrankenPHP) untuk performa WebSocket persistent connection terbaik.

```bash
# Octane + Swoole (direkomendasikan untuk WebSocket)
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --watch

# Atau FrankenPHP (modern, support HTTP/2 & WebSocket native)
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --admin-port=2019
```

**Konfigurasi Octane + Reverb** (`config/octane.php`):

```php
'workers' => env('OCTANE_WORKERS', 4),
'task_workers' => env('OCTANE_TASK_WORKERS', 4),
'max_requests' => 500,

// Reverb butuh persistent connections → worker tidak boleh restart sering
// Set max_requests tinggi atau 0 (unlimited) untuk worker WebSocket
```

### Reverse Proxy (Nginx / Caddy) untuk Production

**Nginx** (`/etc/nginx/sites-available/your-app`):

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # HTTP -> HTTPS redirect
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # HTTP traffic → Laravel Octane (FrankenPHP/Swoole)
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket traffic → Reverb (port 8080)
    location /app/{appKey} {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # WebSocket timeout
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }
}
```

**Caddy** (`Caddyfile`):

```caddy
your-domain.com {
    encode zstd gzip
    
    # Laravel Octane (FrankenPHP/Swoole)
    reverse_proxy 127.0.0.1:8000
    
    # Reverb WebSocket
    reverse_proxy /app/* 127.0.0.1:8080 {
        header_upgrade Connection "Upgrade"
    }
}
```

---

## Konfigurasi Broadcasting (`config/broadcasting.php`)

```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', '127.0.0.1'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'use_tls' => env('REVERB_SCHEME') === 'https',
        ],
        'client_options' => [
            // Guzzle options
            'timeout' => 5,
        ],
    ],
    // ...
],

'default' => env('BROADCAST_DRIVER', 'reverb'),
```

---

## Client Side: Laravel Echo + Reverb (React/Vue/Blade + Vite)

### 1. Install Dependencies

```bash
npm install laravel-echo pusher-js
# atau
yarn add laravel-echo pusher-js
```

### 2. Setup Echo (`resources/js/echo.js`)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
    },
});
```

### 3. Import di `resources/js/app.js` (atau `bootstrap.js`)

```javascript
import './echo';
```

### 4. Vite Config (`vite.config.js`)

```javascript
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
    define: {
        'process.env': process.env,
    },
});
```

### 5. Build Assets

```bash
npm run build
# atau development
npm run dev
```

---

## Broadcasting Events (Server Side)

### 1. Buat Event Broadcast

```bash
php artisan make:event MessageSent --broadcast
```

### 2. Implement Event (`app/Events/MessageSent.php`)

```php
<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public User $sender
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->load('sender'),
            'sender_id' => $this->sender->id,
        ];
    }

    // Optional: custom connection
    public function broadcastConnection(): ?string
    {
        return 'reverb';
    }
}
```

### 3. Channel Authorization (`routes/channels.php`)

```php
<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::where('id', $conversationId)
        ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
        ->exists();
});

// Presence channel untuk online users
Broadcast::channel('online-users', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

### 4. Fire Event

```php
use App\Events\MessageSent;
use App\Models\Message;

$message = Message::create([...]);
broadcast(new MessageSent($message, auth()->user()))->toOthers();
// atau
event(new MessageSent($message, auth()->user()));
```

---

## Client Side: Listen Events

### Vue 3 (Composition API)

```vue
<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const messages = ref([]);

onMounted(() => {
    const channel = window.Echo.private(`conversation.${conversationId}`);
    
    channel.listen('.message.sent', (e) => {
        messages.value.push(e.message);
    });
    
    // Presence channel
    const presence = window.Echo.join('online-users');
    presence.here((users) => {
        console.log('Online:', users);
    }).joining((user) => {
        console.log('Joined:', user);
    }).leaving((user) => {
        console.log('Left:', user);
    });
});

onUnmounted(() => {
    window.Echo.leave(`conversation.${conversationId}`);
    window.Echo.leave('online-users');
});
</script>
```

### Alpine.js (Blade + Livewire)

```blade
<div x-data="{
    messages: [],
    init() {
        const channel = Echo.private(`conversation.{{ $conversation->id }}`);
        channel.listen('.message.sent', (e) => {
            this.messages.push(e.message);
        });
    }
}">
    <template x-for="msg in messages" :key="msg.id">
        <div class="message">{{ msg.content }}</div>
    </template>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        // Alpine sudah memiliki akses ke window.Echo
    });
</script>
@endpush
```

### Vanilla JS / React

```javascript
// React useEffect
useEffect(() => {
    const channel = Echo.private(`conversation.${conversationId}`);
    
    channel.listen('.message.sent', (e) => {
        setMessages(prev => [...prev, e.message]);
    });
    
    return () => {
        window.Echo.leave(`conversation.${conversationId}`);
    };
}, [conversationId]);
```

---

## Private & Presence Channels

| Channel Type | Prefix | Use Case | Auth Required |
|-------------|--------|----------|---------------|
| **Public** | `channel-name` | Public chat, notifications | Tidak |
| **Private** | `private-channel-name` | User-specific, conversations | Ya (`routes/channels.php`) |
| **Presence** | `presence-channel-name` | Online users, collaboration | Ya (return user data) |

### Private Channel

```php
// Event
public function broadcastOn(): PrivateChannel
{
    return new PrivateChannel('orders.' . $this->order->id);
}

// Client
Echo.private(`orders.${orderId}`).listen('.order.shipped', (e) => { ... });

// routes/channels.php
Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return $user->orders()->where('id', $orderId)->exists();
});
```

### Presence Channel

```php
// Event
public function broadcastOn(): PresenceChannel
{
    return new PresenceChannel('room.' . $this->room->id);
}

// Client
Echo.join(`room.${roomId}`)
    .here((users) => { /* user list */ })
    .joining((user) => { /* user joined */ })
    .leaving((user) => { /* user left */ })
    .listen('.message.sent', (e) => { ... });

// routes/channels.php
Broadcast::channel('room.{roomId}', function (User $user, int $roomId) {
    if ($user->rooms()->where('id', $roomId)->exists()) {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatar];
    }
});
```

---

## Broadcasting ke Channels Tertentu (Whisper / Client Events)

Kirim event **dari client ke client lain** tanpa through server Laravel:

```javascript
// Client A mengirim whisper ke channel
Echo.private('chat.1')
    .whisper('typing', { user_id: 1, name: 'John' });

// Client B menerima whisper
Echo.private('chat.1')
    .listenForWhisper('typing', (e) => {
        console.log(`${e.name} is typing...`);
    });
```

> **Note:** Whisper hanya bekerja di **private/presence channels**, tidak di public channels.

---

## Broadcasting dari Queue (Background Jobs)

Default: Broadcast events **sync** (blocking). Gunakan queue untuk non-blocking:

```php
// Event implements ShouldBroadcastNow → sync (default)
// Event implements ShouldBroadcast → queued (via queue worker)

class MessageSent implements ShouldBroadcast // Bukan ShouldBroadcastNow
{
    // ...
}
```

**Queue config** (`config/queue.php`):

```php
'connections' => [
    'reverb' => [
        'driver' => 'redis', // atau database
        'connection' => 'default',
        'queue' => 'reverb', // queue dedicated untuk broadcast
    ],
],
```

**Jalankan worker dedicated:**

```bash
php artisan queue:work reverb --queue=reverb --sleep=3 --tries=3
```

---

## Testing Broadcasting

### 1. Fake Broadcast (Feature Test)

```php
<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\{User, Conversation, Message};
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    public function test_message_broadcasts_to_conversation_channel()
    {
        Broadcast::fake();
        
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
        ]);
        
        // Trigger event
        event(new MessageSent($message, $user));
        
        // Assert
        Broadcast::assertDispatched(MessageSent::class, function ($event) use ($message, $conversation) {
            return $event->message->id === $message->id
                && $event->broadcastOn()[0]->name === "conversation.{$conversation->id}";
        });
    }
    
    public function test_private_channel_authorization()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->participants()->attach($user->id);
        
        $this->actingAs($user)
            ->post("/broadcasting/auth", [
                'channel_name' => "private-conversation.{$conversation->id}",
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }
}
```

### 2. Test Client Events (Whisper)

```php
Broadcast::fake();

// Client event tidak bisa di-fake langsung, test via JavaScript/E2E (Dusk/Pest)
```

---

## Integrasi Livewire + Reverb

Livewire 4 dapat mendengarkan broadcast events dengan mudah menggunakan component lifecycle hooks atau Alpine.js integration. Berikut pola implementasi di project dengan anonymous Livewire components:

### 1. Component Livewire yang Listen ke Broadcast

**File:** `resources/views/pages/chat/⚡real-time-messages.blade.php`

```php
<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public Conversation $conversation;
    public array $messages = [];
    public string $newMessage = '';

    public function mount(Conversation $conversation): void
    {
        $this->conversation = $conversation;
        $this->loadMessages();
    }

    #[On('message-sent')]
    public function onMessageSent($data): void
    {
        $this->loadMessages();
        $this->dispatch('scroll-to-bottom');
    }

    public function sendMessage(): void
    {
        if (blank($this->newMessage)) {
            return;
        }

        $message = $this->conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => $this->newMessage,
        ]);

        broadcast(new MessageSent($message, auth()->user()))->toOthers();

        $this->newMessage = '';
        $this->loadMessages();
    }

    private function loadMessages(): void
    {
        $this->messages = $this->conversation
            ->messages()
            ->with('sender')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->toArray();
    }

    public function render()
    {
        return view('pages.chat.⚡real-time-messages');
    }
} ?>
```

**Blade template:**

```blade
<div class="space-y-4">
    <div class="h-96 overflow-y-auto border rounded-lg p-4 bg-gray-50" x-data @scroll-to-bottom.window="$el.scrollTop = $el.scrollHeight">
        @forelse($messages as $msg)
            <div class="mb-4 {{ $msg['user_id'] == auth()->id() ? 'text-right' : '' }}">
                <div class="text-xs text-gray-500">{{ $msg['sender']['name'] }}</div>
                <div class="inline-block max-w-xs px-4 py-2 rounded-lg {{ $msg['user_id'] == auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                    {{ $msg['content'] }}
                </div>
                <div class="text-xs text-gray-400">{{ $msg['created_at'] }}</div>
            </div>
        @empty
            <div class="text-center text-gray-400">Tidak ada pesan</div>
        @endforelse
    </div>

    <form wire:submit="sendMessage" class="flex gap-2">
        <textarea wire:model.live="newMessage" placeholder="Tulis pesan..." class="flex-1 rounded border px-4 py-2"></textarea>
        <flux:button type="submit">Kirim</flux:button>
    </form>
</div>

@script
<script>
    Echo.private(`conversation.{{ $conversation->id }}`)
        .listen('.message.sent', (e) => {
            Livewire.dispatch('message-sent', { message: e.message });
        });
</script>
@endscript
```

### 2. Presence Channel dengan Livewire (Online Users)

```php
#[On('online-user-joining')]
public function onUserJoining($data): void
{
    // Update UI dengan list online users
    $this->dispatch('refresh-online-users');
}

public function render()
{
    return view('pages.chat.⚡participants', [
        'onlineCount' => cache()->get("conversation.{$this->conversation->id}.online", 0),
    ]);
}
```

**Client-side:**

```javascript
Echo.join(`conversation.{{ $conversation->id }}`)
    .here((users) => {
        Livewire.dispatch('online-user-joining', { count: users.length });
    })
    .joining((user) => {
        Livewire.dispatch('online-user-joining', { count: users.length });
    })
    .leaving((user) => {
        Livewire.dispatch('online-user-leaving', { count: users.length });
    });
```

---

## Real-time Chat dengan Flux UI

Contoh lengkap fitur chat real-time menggunakan Livewire + Flux UI + Reverb untuk project ini:

### Event & Model

**Event** (`app/Events/ChatMessageBroadcasted.php`):

```php
<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageBroadcasted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public User $sender,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'sender_avatar' => $this->sender->avatar_url,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

### Livewire Component dengan Flux UI

**File:** `resources/views/pages/chat/⚡conversation.blade.php`

```php
<?php

use App\Events\ChatMessageBroadcasted;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public Conversation $conversation;
    public string $content = '';
    public bool $isLoading = false;
    public int $perPage = 20;

    public function mount(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    #[Computed]
    public function messages()
    {
        return $this->conversation
            ->messages()
            ->with('sender')
            ->latest('created_at')
            ->paginate($this->perPage);
    }

    #[On('message.posted')]
    public function onMessagePosted($data): void
    {
        // Reload messages jika bukan dari sender
        if ($data['sender_id'] !== auth()->id()) {
            $this->dispatch('refresh-messages');
        }
    }

    public function sendMessage(): void
    {
        $this->validate(['content' => 'required|string|max:1000']);

        $message = $this->conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => $this->content,
        ]);

        broadcast(new ChatMessageBroadcasted($message, auth()->user()))->toOthers();

        $this->content = '';
        $this->dispatch('scroll-to-latest');
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function render()
    {
        return view('pages.chat.⚡conversation');
    }
} ?>
```

**Blade template dengan Flux UI:**

```blade
<div class="flex h-screen flex-col">
    {{-- Header --}}
    <flux:navbar class="border-b">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-gray-300"></div>
            <div>
                <h2 class="font-semibold">{{ $conversation->title }}</h2>
                <p class="text-xs text-gray-500">{{ $conversation->participants_count }} peserta</p>
            </div>
        </div>
    </flux:navbar>

    {{-- Messages --}}
    <div class="flex-1 overflow-y-auto space-y-4 p-4" x-data @scroll-to-latest.window="setTimeout(() => $el.scrollTop = $el.scrollHeight, 100)">
        @if($messages->count() === 0)
            <div class="flex h-full items-center justify-center">
                <p class="text-gray-400">Belum ada pesan</p>
            </div>
        @else
            @foreach($messages->reverse() as $message)
                <div class="flex {{ $message->user_id === auth()->id() ? 'justify-end' : 'justify-start' }} gap-2">
                    @if($message->user_id !== auth()->id())
                        <img src="{{ $message->sender->avatar_url }}" class="h-8 w-8 rounded-full">
                    @endif
                    
                    <div class="flex flex-col {{ $message->user_id === auth()->id() ? 'items-end' : 'items-start' }}">
                        @if($message->user_id !== auth()->id())
                            <span class="text-xs font-semibold text-gray-600">{{ $message->sender->name }}</span>
                        @endif
                        
                        <div class="rounded-lg px-4 py-2 {{ $message->user_id === auth()->id() ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }}">
                            {{ $message->content }}
                        </div>
                        
                        <span class="text-xs text-gray-400">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @endforeach

            @if($messages->hasMorePages())
                <div class="flex justify-center pt-4">
                    <flux:button variant="subtle" wire:click="loadMore">Muat lebih lama</flux:button>
                </div>
            @endif
        @endif
    </div>

    {{-- Input --}}
    <flux:card class="border-t border-x-0 border-b-0 rounded-none p-4">
        <form wire:submit="sendMessage" class="flex gap-2">
            <textarea wire:model.defer="content" 
                placeholder="Ketik pesan..." 
                class="flex-1 resize-none rounded border border-gray-300 p-2 focus:border-blue-500 focus:outline-none"
                rows="3"></textarea>
            <flux:button type="submit" :disabled="blank($content)">
                <flux:icon.paper-airplane class="h-5 w-5" />
            </flux:button>
        </form>
    </flux:card>
</div>

@script
<script>
    const conversationId = {{ $conversation->id }};
    
    Echo.private(`chat.conversation.${conversationId}`)
        .listen('.message.posted', (e) => {
            Livewire.dispatch('message.posted', e);
        });
    
    Livewire.on('refresh-messages', () => {
        Livewire.dispatch('refresh');
    });
</script>
@endscript
```

---

## Security Best Practices untuk Broadcast

### 1. Input Validation Sebelum Broadcast

```php
public function sendMessage(): void
{
    // Validasi & sanitize
    $this->validate([
        'content' => 'required|string|max:1000|not_regex:/javascript|onclick/i',
    ]);

    // Cegah injection HTML/JS
    $content = htmlspecialchars($this->content, ENT_QUOTES, 'UTF-8');

    $message = $this->conversation->messages()->create([
        'user_id' => auth()->id(),
        'content' => $content,
    ]);

    broadcast(new ChatMessageBroadcasted($message, auth()->user()))->toOthers();
}
```

### 2. Authorization di Channel Subscription

**File:** `routes/channels.php`

```php
<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.conversation.{conversationId}', function (User $user, int $conversationId) {
    // Cek apakah user adalah participant
    return Conversation::query()
        ->where('id', $conversationId)
        ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
        ->exists();
});
```

### 3. Rate Limiting Client Events (Whisper)

```javascript
// Client-side: throttle whisper events
let lastTypingTime = 0;

Echo.private(`chat.conversation.${conversationId}`)
    .on('typing', (user) => {
        const now = Date.now();
        if (now - lastTypingTime < 500) return; // 500ms throttle
        lastTypingTime = now;
        console.log(`${user.name} sedang mengetik...`);
    });
```

### 4. Sensitive Data - Jangan Broadcast Langsung

```php
// ❌ JANGAN - sensitive data ke public
public function broadcastWith(): array
{
    return [
        'message' => $this->message, // bisa expose password hash!
    ];
}

// ✅ BENAR - hanya data yang diperlukan
public function broadcastWith(): array
{
    return [
        'id' => $this->message->id,
        'content' => $this->message->content,
        'sender_id' => $this->sender->id,
        'sender_name' => $this->sender->name,
        'created_at' => $this->message->created_at,
    ];
}
```

### 5. Laravel Pulse untuk Monitoring Broadcast

```bash
composer require laravel/pulse
php artisan pulse:install
```

---

## Connection Management & Reliability

### 1. Detect Connection State (Client-side)

```javascript
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✓ Terhubung ke Reverb');
    document.body.classList.remove('offline');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('✗ Putus dari Reverb');
    document.body.classList.add('offline');
});

window.Echo.connector.pusher.connection.bind('error', (error) => {
    console.error('Reverb error:', error);
});
```

### 2. Auto-reconnect & Exponential Backoff

Update `resources/js/echo.js`:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Konfigurasi reconnect
Pusher.logToConsole = import.meta.env.DEV;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
    },
    // Reconnect config
    pong_timeout: 30000,
    activity_timeout: 45000,
    unavailable_timeout: 10000,
});

// Manual reconnect handler
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;
const baseDelay = 1000;

function attemptReconnect() {
    if (reconnectAttempts >= maxReconnectAttempts) {
        console.warn('Max reconnect attempts reached');
        return;
    }

    const delay = baseDelay * Math.pow(2, reconnectAttempts); // Exponential backoff
    reconnectAttempts++;

    console.log(`Attempting reconnect in ${delay}ms...`);
    setTimeout(() => {
        window.Echo.connector.pusher.connection.disconnect();
        window.Echo.connector.pusher.connection.connect();
    }, delay);
}

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    attemptReconnect();
});
```

### 3. Graceful Fallback untuk Browser Lama

```javascript
// Detect browser support
if (!window.WebSocket && !window.MozWebSocket) {
    console.warn('WebSocket tidak support');
    // Fallback ke polling atau disable features
    document.body.classList.add('no-websocket');
}
```

### 4. Offline Queue (untuk messaging apps)

```php
// Livewire: simpan pesan ke local storage sebelum send
// JavaScript akan retry saat reconnect
```

**JavaScript Fallback:**

```javascript
class MessageQueue {
    constructor() {
        this.queue = JSON.parse(localStorage.getItem('messageQueue') || '[]');
    }

    add(message) {
        this.queue.push({
            ...message,
            timestamp: Date.now(),
            attempt: 0,
        });
        this.save();
    }

    save() {
        localStorage.setItem('messageQueue', JSON.stringify(this.queue));
    }

    async flush() {
        for (let msg of this.queue) {
            try {
                await fetch('/api/messages', {
                    method: 'POST',
                    body: JSON.stringify(msg),
                });
                this.queue = this.queue.filter(m => m.timestamp !== msg.timestamp);
                this.save();
            } catch (err) {
                msg.attempt++;
                if (msg.attempt > 3) {
                    this.queue = this.queue.filter(m => m.timestamp !== msg.timestamp);
                }
            }
        }
    }
}

const messageQueue = new MessageQueue();

// On reconnect, flush queue
window.Echo.connector.pusher.connection.bind('connected', () => {
    messageQueue.flush();
});
```

---

## Integration dengan Laravel Features

### 1. Broadcast + Jobs (Background Processing)

Event yang heavy-lift → gunakan queue:

```php
class MessageProcessed implements ShouldBroadcast
{
    // Jangan gunakan ShouldBroadcastNow
    // Biarkan queue worker process, lalu broadcast ke client
}

class ProcessMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public Message $message) {}

    public function handle(): void
    {
        // Do expensive work
        $this->message->update([
            'is_processed' => true,
            'processed_at' => now(),
        ]);

        // Notify clients
        broadcast(new MessageProcessed($this->message))->toOthers();
    }
}

// Dispatch
ProcessMessage::dispatch($message);
```

### 2. Scheduled Tasks + Broadcast

```php
// app/Console/Kernel.php
Schedule::call(function () {
    $online = Cache::get('online_users', []);
    broadcast(new OnlineStatusUpdate($online))->toOthers();
})->everyMinute();
```

### 3. Integration dengan WhatsApp/Email Notifications

```php
class MessageSent implements ShouldBroadcast
{
    public function handle(): void
    {
        // Broadcast ke web client
        broadcast($this)->toOthers();

        // Kirim notifikasi external jika user offline
        if (!$this->sender->isOnline()) {
            SendNotificationViaWhatsApp::dispatch($this->message);
            SendNotificationViaEmail::dispatch($this->message);
        }
    }
}
```

---

## Debugging & Monitoring

### 1. Laravel Telescope

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

Telescope tab **Broadcasting** menampilkan:
- Events yang di-dispatch
- Channel tujuan
- Payload
- Timing

### 2. Reverb Debug Mode

```bash
php artisan reverb:start --debug
```

Output log:
```
[2024-01-15 10:30:45] REVERB DEBUG: New connection from 127.0.0.1
[2024-01-15 10:30:46] REVERB DEBUG: Subscribed to private-conversation.1
[2024-01-15 10:30:47] REVERB DEBUG: Broadcasting event "message.sent" to 3 connections
```

### 3. Log Channel Broadcasting

```php
// config/logging.php
'channels' => [
    'reverb' => [
        'driver' => 'daily',
        'path' => storage_path('logs/reverb.log'),
        'level' => 'debug',
    ],
],
```

```php
// AppServiceProvider.php
public function boot(): void
{
    \Illuminate\Support\Facades\Broadcast::listen(function ($event) {
        logger()->channel('reverb')->debug('Broadcast event', [
            'event' => get_class($event),
            'channels' => $event->broadcastOn(),
            'payload' => $event->broadcastWith(),
        ]);
    });
}
```

### 4. Client-side Debug

```javascript
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('Reverb connected!');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('Reverb disconnected!');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('Reverb error:', err);
});
```

---

## Scaling & Production Checklist

### Horizontal Scaling (Multiple Reverb Instances)

```
                    ┌─────────────┐
                    │  Load       │
                    │  Balancer   │
                    │  (Nginx/    │
                    │   Caddy)    │
                    └──────┬──────┘
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
    ┌──────────┐     ┌──────────┐     ┌──────────┐
    │ Reverb 1 │     │ Reverb 2 │     │ Reverb 3 │
    │ :8080    │     │ :8081    │     │ :8082    │
    └────┬─────┘     └────┬─────┘     └────┬─────┘
         │                │                │
         └────────────────┼────────────────┘
                          ▼
                 ┌─────────────────┐
                 │  Redis Pub/Sub  │
                 │  (Shared)       │
                 └─────────────────┘
```

**Config `config/reverb.php` untuk scaling:**

```php
'applications' => [
    'main' => [
        'id' => env('REVERB_APP_ID'),
        'name' => env('APP_NAME'),
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_SECRET'),
        'enable_client_messages' => true,
        'enable_statistics' => true,
    ],
],

// Redis untuk scaling horizontal (PUB/SUB antar instance Reverb)
'redis' => [
    'client' => 'predis',
    'cluster' => false,
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', 6379),
    'password' => env('REDIS_PASSWORD'),
    'database' => env('REVERB_REDIS_DB', 2),
],
```

### Production Checklist

- [ ] **SSL/TLS** via reverse proxy (Nginx/Caddy/Cloudflare)
- [ ] **Octane/FrankenPHP/Swoole** untuk persistent connections
- [ ] **Redis** untuk horizontal scaling (pub/sub antar Reverb instances)
- [ ] **Queue worker dedicated** untuk broadcast (`queue:work --queue=reverb`)
- [ ] **Monitoring**: Laravel Telescope, Laravel Pulse, atau Prometheus/Grafana
- [ ] **Rate limiting** di Nginx untuk `/app/{appKey}` endpoint
- [ ] **Health check** endpoint untuk load balancer
- [ ] **Log rotation** untuk Reverb logs
- [ ] **Backup strategy** untuk Redis (persistence)

---

## Troubleshooting Umum

| Masalah | Penyebab | Solusi |
|---------|----------|--------|
| `WebSocket connection failed` | Port salah / firewall | Cek `REVERB_PORT`, firewall, proxy config |
| `401 Unauthorized` di private channel | Auth gagal | Cek `routes/channels.php`, CSRF token, login user |
| Event tidak terkirim ke client | Queue tidak jalan / sync | Jalankan `queue:work`, cek `ShouldBroadcast` vs `ShouldBroadcastNow` |
| `Connection closed` sering | Timeout proxy | Tambah `proxy_read_timeout 86400` di Nginx |
| Presence channel `here()` kosong | User tidak join | Pastikan `Echo.join()` dipanggil, channel authorized |
| Whisper tidak jalan | Public channel | Whisper hanya di `private-*` / `presence-*` |
| Memory leak di Octane | Worker tidak restart | Set `max_requests` tinggi, monitor memory |

---

## Referensi Resmi

- **Dokumentasi Resmi:** https://reverb.laravel.com
- **GitHub:** https://github.com/laravel/reverb
- **Laravel Broadcasting Docs:** https://laravel.com/docs/13.x/broadcasting
- **Laravel Echo:** https://github.com/laravel/echo
- **Reverb + Octane:** https://reverb.laravel.com/docs/scaling/horizontal-scaling

---

## Ringkasan Perintah Berguna

```bash
# Install & Setup
composer require laravel/reverb
php artisan reverb:install
npm install laravel-echo pusher-js
npm run build

# Development
php artisan reverb:start --debug
php artisan serve
npm run dev

# Production (Octane + FrankenPHP)
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080

# Queue worker untuk broadcast
php artisan queue:work --queue=reverb --sleep=3

# Testing
php artisan test --filter=BroadcastingTest

# Debug
php artisan reverb:start --debug
php artisan telescope:install
```

---

*Dokumen ini dibuat untuk project Laravel 13 + Livewire 4 + Flux UI. Sesuaikan versi package sesuai kebutuhan project.*