# Panduan Integrasi Supabase dengan Laravel 13

Langkah step-by-step menggunakan database API eksternal Supabase.

---

## 1. Persiapan Akun Supabase

1. Buka [https://supabase.com](https://supabase.com) dan buat akun.
2. Buat **New Project**, pilih region terdekat.
3. Setelah project dibuat, buka **Settings > API** dan catat:
   - **Project URL** → `https://<project-id>.supabase.co`
   - **anon (public) key** → untuk akses client-side
   - **service_role key** → untuk akses server-side (Laravel)

> **Peringatan Keamanan:** `service_role` key **bypass seluruh Row Level Security (RLS)**. Jangan pernah expose key ini di client-side (JavaScript, file publik, atau commit ke repository). Gunakan hanya di backend Laravel melalui `config/services.php` dan `.env`.

---

## 2. Buat Tabel di Supabase

1. Buka **Table Editor** di dashboard Supabase.
2. Klik **New Table**, contoh buat tabel `products`:

| Column       | Type      | Default         | Nullable |
|--------------|-----------|-----------------|----------|
| id           | uuid      | gen_random_uuid | No       |
| name         | text      | -               | No       |
| description  | text      | -               | Yes      |
| price        | numeric   | 0               | No       |
| stock        | int4      | 0               | No       |
| created_at   | timestamptz | now()         | No       |

3. Aktifkan **Row Level Security (RLS)** sesuai kebutuhan.

---

## 3. Konfigurasi Laravel

### 3.1. Tambahkan Environment Variables

Buka file `.env` dan tambahkan:

```dotenv
SUPABASE_URL=https://<project-id>.supabase.co
SUPABASE_KEY=your-service-role-key-here
```

### 3.2. Tambahkan ke Config Services

Buka `config/services.php` dan tambahkan entry baru di dalam array `return`:

```php
'supabase' => [
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_KEY'),
],
```

> **Catatan:** File `config/services.php` sudah berisi konfigurasi `postmark`, `resend`, `ses`, `slack`, dan `google`. Tambahkan entry `supabase` di bawah entry yang sudah ada.

---

## 4. Buat Supabase Service Class

Buat file `app/Services/SupabaseService.php` menggunakan artisan:

```bash
php artisan make:class Services/SupabaseService --no-interaction
```

Kemudian isi dengan:

```php
<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SupabaseService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.supabase.url').'/rest/v1';
        $this->apiKey = config('services.supabase.key');
    }

    /**
     * @return array<string, string>
     */
    protected function headers(bool $count = false): array
    {
        $prefer = 'return=representation';

        if ($count) {
            $prefer .= ', count=exact';
        }

        return [
            'apikey' => $this->apiKey,
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => $prefer,
        ];
    }

    /**
     * Ambil semua data dari tabel.
     *
     * @param  array<string, string>  $query
     * @return array<int, array<string, mixed>>
     */
    public function get(string $table, array $query = []): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->get("{$this->baseUrl}/{$table}", $query);

            return $response->json();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'GET',
                'table' => $table,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return [];
        }
    }

    /**
     * Ambil data dengan pagination menggunakan Range header.
     *
     * @param  array<string, string>  $query
     */
    public function paginate(string $table, int $page = 1, int $perPage = 10, array $query = []): LengthAwarePaginator
    {
        $start = ($page - 1) * $perPage;
        $end = $start + $perPage - 1;

        try {
            $response = Http::withHeaders($this->headers(count: true))
                ->withHeaders(['Range' => "{$start}-{$end}"])
                ->throw()
                ->get("{$this->baseUrl}/{$table}", $query);

            $data = $response->json();

            // Parse total dari header Content-Range, format: "0-9/42"
            $contentRange = $response->header('Content-Range') ?? '';
            $total = (int) (explode('/', $contentRange)[1] ?? count($data));

            return new LengthAwarePaginator(
                Collection::make($data),
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ],
            );
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'PAGINATE',
                'table' => $table,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return new LengthAwarePaginator(Collection::make([]), 0, $perPage, $page);
        }
    }

    /**
     * Ambil satu data berdasarkan kolom key (default: id).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $table, string $id, string $key = 'id'): ?array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->get("{$this->baseUrl}/{$table}", [
                    $key => "eq.{$id}",
                ]);

            $data = $response->json();

            return $data[0] ?? null;
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'FIND',
                'table' => $table,
                'key' => $key,
                'id' => $id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return null;
        }
    }

    /**
     * Insert data ke tabel.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function insert(string $table, array $data): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->post("{$this->baseUrl}/{$table}", $data);

            return $response->json();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'INSERT',
                'table' => $table,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return [];
        }
    }

    /**
     * Update data berdasarkan kolom key (default: id).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function update(string $table, string $id, array $data, string $key = 'id'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->patch("{$this->baseUrl}/{$table}?{$key}=eq.{$id}", $data);

            return $response->json();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'UPDATE',
                'table' => $table,
                'key' => $key,
                'id' => $id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return [];
        }
    }

    /**
     * Hapus data berdasarkan kolom key (default: id).
     */
    public function delete(string $table, string $id, string $key = 'id'): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->delete("{$this->baseUrl}/{$table}?{$key}=eq.{$id}");

            return $response->successful();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'DELETE',
                'table' => $table,
                'key' => $key,
                'id' => $id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return false;
        }
    }
}
```

> **Catatan:** Method `find`, `update`, dan `delete` menerima parameter opsional `$key` (default: `'id'`). Gunakan jika primary key tabel bukan `id`, contoh: `$supabase->find('orders', 'ORD-001', 'order_number')`.

---

## 5. Daftarkan Service di AppServiceProvider

Buka `app/Providers/AppServiceProvider.php` dan tambahkan di method `register()` (yang sudah berisi singleton `LoginResponse` dan `RegisterResponse`):

```php
use App\Services\SupabaseService;

// Di dalam method register(), tambahkan:
$this->app->singleton(SupabaseService::class);
```

---

## 6. Pendekatan Livewire SFC (Rekomendasi)

Project ini menggunakan **Livewire Single File Component (SFC)** dengan prefix `⚡` sebagai konvensi utama. Berikut cara membuat halaman product menggunakan pendekatan yang sama.

### 6.1. Definisikan Route

Buka `routes/web.php` dan tambahkan di dalam group middleware `['auth', 'verified', 'login-otp']`:

```php
Route::livewire('products', 'pages::products.index')->name('products.index');
```

### 6.2. Buat Livewire SFC Page

Buat file `resources/views/pages/products/⚡index.blade.php`:

```blade
<?php

use App\Services\SupabaseService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Products')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?string $editingId = null;

    #[Validate(['required', 'string', 'max:255'])]
    public string $name = '';

    #[Validate(['nullable', 'string'])]
    public string $description = '';

    #[Validate(['required', 'numeric', 'min:0'])]
    public float|string $price = 0;

    #[Validate(['required', 'integer', 'min:0'])]
    public int $stock = 0;

    public ?string $productToDeleteId = null;

    public ?string $productToDeleteName = null;

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $supabase = app(SupabaseService::class);

        $query = ['order' => 'created_at.desc'];

        if ($this->search !== '') {
            $query['name'] = "ilike.%{$this->search}%";
        }

        return $supabase->paginate('products', $this->getPage(), 10, $query);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function store(): void
    {
        $validated = $this->validate();

        $supabase = app(SupabaseService::class);
        $supabase->insert('products', $validated);

        $this->showFormModal = false;
        $this->resetForm();
        $this->resetPage();
        unset($this->products);

        Flux::toast(variant: 'success', text: 'Produk berhasil ditambahkan.');
    }

    public function confirmDelete(string $id, string $name): void
    {
        $this->productToDeleteId = $id;
        $this->productToDeleteName = $name;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->productToDeleteId) {
            return;
        }

        $supabase = app(SupabaseService::class);
        $supabase->delete('products', $this->productToDeleteId);

        $this->showDeleteModal = false;
        $this->productToDeleteId = null;
        $this->productToDeleteName = null;
        $this->resetPage();
        unset($this->products);

        Flux::toast(variant: 'success', text: 'Produk berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->price = 0;
        $this->stock = 0;
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Kelola data produk dari Supabase.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Tambah Produk') }}
        </flux:button>
    </div>

    <div class="max-w-sm">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Cari produk...') }}"
            clearable
        />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Harga') }}</flux:table.column>
            <flux:table.column>{{ __('Stok') }}</flux:table.column>
            <flux:table.column>{{ __('Aksi') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->products as $product)
                <flux:table.row wire:key="product-{{ $product['id'] }}">
                    <flux:table.cell class="font-medium">{{ $product['name'] }}</flux:table.cell>
                    <flux:table.cell>Rp {{ number_format($product['price'], 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>{{ $product['stock'] }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button
                            size="sm"
                            icon="trash"
                            variant="ghost"
                            class="text-red-500 hover:text-red-600 dark:text-red-400"
                            wire:click="confirmDelete('{{ $product['id'] }}', '{{ $product['name'] }}')"
                        >
                            {{ __('Hapus') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                            <flux:icon name="cube" class="size-10 opacity-40" />
                            <p class="text-sm">
                                {{ $search ? __('Tidak ada produk yang cocok dengan pencarian.') : __('Belum ada produk.') }}
                            </p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->products->hasPages())
        <div>
            {{ $this->products->links() }}
        </div>
    @endif

    <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
        <flux:heading size="lg">{{ __('Tambah Produk') }}</flux:heading>
        <flux:subheading>{{ __('Isi data produk baru.') }}</flux:subheading>

        <form wire:submit="store" class="mt-6 space-y-5">
            <flux:field>
                <flux:label>{{ __('Nama') }}</flux:label>
                <flux:input wire:model="name" type="text" placeholder="Nama produk" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Deskripsi') }}</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="Deskripsi produk" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Harga') }}</flux:label>
                <flux:input wire:model="price" type="number" placeholder="0" />
                <flux:error name="price" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Stok') }}</flux:label>
                <flux:input wire:model="stock" type="number" placeholder="0" />
                <flux:error name="stock" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Simpan') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="flex flex-col items-center gap-4 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon name="trash" class="size-7 text-red-500" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Hapus Produk') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('Apakah Anda yakin ingin menghapus produk') }}
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $productToDeleteName }}</span>?
                </flux:text>
            </div>
        </div>

        <div class="mt-6 flex justify-center gap-3">
            <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">
                {{ __('Batal') }}
            </flux:button>
            <flux:button type="button" variant="danger" wire:click="delete">
                {{ __('Ya, Hapus') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
```

---

## 7. Pendekatan Controller (Alternatif)

Jika lebih memilih pendekatan controller tradisional:

```bash
php artisan make:controller ProductController --no-interaction
```

Isi `app/Http/Controllers/ProductController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected SupabaseService $supabase
    ) {}

    public function index(): View
    {
        $products = $this->supabase->get('products', [
            'order' => 'created_at.desc',
        ]);

        return view('products.index', compact('products'));
    }

    public function show(string $id): View
    {
        $product = $this->supabase->find('products', $id);

        abort_unless($product, 404);

        return view('products.show', compact('product'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $this->supabase->insert('products', $validated);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $this->supabase->update('products', $id, $validated);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diupdate.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->supabase->delete('products', $id);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
```

Route untuk controller (di `routes/web.php`):

```php
use App\Http\Controllers\ProductController;

Route::middleware(['auth', 'verified', 'login-otp'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
});
```

---

## 8. Query Lanjutan Supabase REST API

### 8.1. Filter

```php
$supabase->get('products', [
    'price' => 'gte.50000',
    'stock' => 'gt.0',
]);
```

### 8.2. Select Kolom Tertentu

```php
$supabase->get('products', [
    'select' => 'id,name,price',
]);
```

### 8.3. Pagination

Gunakan method `paginate()` dari `SupabaseService` yang sudah mengembalikan `LengthAwarePaginator`:

```php
$supabase = app(SupabaseService::class);

$products = $supabase->paginate('products', page: 1, perPage: 10, query: [
    'order' => 'created_at.desc',
]);

$products->links(); // render pagination di Blade
```

> **Catatan:** Method `paginate()` menggunakan header `Range` (misal `0-9` untuk halaman 1) dan `Prefer: count=exact` untuk mendapatkan total record dari header `Content-Range`. Anda tidak perlu menulis HTTP call manual.

### 8.4. Relasi (Foreign Table)

```php
$supabase->get('orders', [
    'select' => '*,products(name,price)',
]);
```

---

## 9. Testing

Buat test file menggunakan PHPUnit (sesuai konvensi project):

```bash
php artisan make:test SupabaseServiceTest --phpunit --no-interaction
```

Isi `tests/Feature/SupabaseServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseServiceTest extends TestCase
{
    public function test_dapat_mengambil_products_dari_supabase(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([
                ['id' => 'uuid-1', 'name' => 'Produk A', 'price' => 10000, 'stock' => 5],
                ['id' => 'uuid-2', 'name' => 'Produk B', 'price' => 20000, 'stock' => 3],
            ]),
        ]);

        $service = app(SupabaseService::class);
        $products = $service->get('products');

        $this->assertCount(2, $products);
        $this->assertEquals('Produk A', $products[0]['name']);
    }

    public function test_dapat_mengambil_satu_product_berdasarkan_id(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([
                ['id' => 'uuid-1', 'name' => 'Produk A', 'price' => 10000, 'stock' => 5],
            ]),
        ]);

        $service = app(SupabaseService::class);
        $product = $service->find('products', 'uuid-1');

        $this->assertNotNull($product);
        $this->assertEquals('Produk A', $product['name']);
    }

    public function test_find_mengembalikan_null_jika_tidak_ditemukan(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([]),
        ]);

        $service = app(SupabaseService::class);
        $product = $service->find('products', 'uuid-tidak-ada');

        $this->assertNull($product);
    }

    public function test_dapat_insert_product_ke_supabase(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([
                ['id' => 'uuid-new', 'name' => 'Produk Baru', 'price' => 15000, 'stock' => 10],
            ]),
        ]);

        $service = app(SupabaseService::class);
        $result = $service->insert('products', [
            'name' => 'Produk Baru',
            'price' => 15000,
            'stock' => 10,
        ]);

        $this->assertEquals('Produk Baru', $result[0]['name']);
    }

    public function test_dapat_update_product_di_supabase(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([
                ['id' => 'uuid-1', 'name' => 'Produk Updated', 'price' => 25000, 'stock' => 8],
            ]),
        ]);

        $service = app(SupabaseService::class);
        $result = $service->update('products', 'uuid-1', [
            'name' => 'Produk Updated',
            'price' => 25000,
            'stock' => 8,
        ]);

        $this->assertEquals('Produk Updated', $result[0]['name']);
    }

    public function test_dapat_delete_product_dari_supabase(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response([], 200),
        ]);

        $service = app(SupabaseService::class);
        $result = $service->delete('products', 'uuid-1');

        $this->assertTrue($result);
    }

    public function test_dapat_paginate_products_dari_supabase(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response(
                [
                    ['id' => 'uuid-1', 'name' => 'Produk A', 'price' => 10000, 'stock' => 5],
                    ['id' => 'uuid-2', 'name' => 'Produk B', 'price' => 20000, 'stock' => 3],
                ],
                206,
                ['Content-Range' => '0-1/42'],
            ),
        ]);

        $service = app(SupabaseService::class);
        $paginator = $service->paginate('products', 1, 10);

        $this->assertCount(2, $paginator);
        $this->assertEquals(42, $paginator->total());
        $this->assertEquals('Produk A', $paginator->first()['name']);
    }

    public function test_get_mengembalikan_array_kosong_saat_api_error(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $service = app(SupabaseService::class);
        $products = $service->get('products');

        $this->assertEmpty($products);
    }

    public function test_delete_mengembalikan_false_saat_api_error(): void
    {
        Http::fake([
            '*/rest/v1/products*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $service = app(SupabaseService::class);
        $result = $service->delete('products', 'uuid-tidak-ada');

        $this->assertFalse($result);
    }
}
```

Jalankan test:

```bash
php artisan test --compact --filter=SupabaseServiceTest
```

---

## 10. Referensi

- [Supabase REST API Docs](https://supabase.com/docs/guides/api)
- [Supabase PostgREST Operators](https://postgrest.org/en/stable/references/api/tables_views.html)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)

---

## Ringkasan Alur

```
.env (URL + KEY)
    ↓
config/services.php
    ↓
SupabaseService (HTTP Client → Supabase REST API)
    ↓
Livewire SFC (⚡index.blade.php) / Controller
    ↓
Flux UI Components (Blade View)
```
