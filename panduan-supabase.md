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
    protected function headers(): array
    {
        return [
            'apikey' => $this->apiKey,
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
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
     * Ambil satu data berdasarkan ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $table, string $id): ?array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->get("{$this->baseUrl}/{$table}", [
                    'id' => "eq.{$id}",
                ]);

            $data = $response->json();

            return $data[0] ?? null;
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'FIND',
                'table' => $table,
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
     * Update data berdasarkan ID.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function update(string $table, string $id, array $data): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->patch("{$this->baseUrl}/{$table}?id=eq.{$id}", $data);

            return $response->json();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'UPDATE',
                'table' => $table,
                'id' => $id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return [];
        }
    }

    /**
     * Hapus data berdasarkan ID.
     */
    public function delete(string $table, string $id): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->throw()
                ->delete("{$this->baseUrl}/{$table}?id=eq.{$id}");

            return $response->successful();
        } catch (RequestException $e) {
            logger()->error('Supabase API Error', [
                'method' => 'DELETE',
                'table' => $table,
                'id' => $id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return false;
        }
    }
}
```

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
Route::livewire('/products', 'pages::products.index')->name('products.index');
```

### 6.2. Buat Livewire SFC Page

Buat file `resources/views/pages/products/⚡index.blade.php`:

```blade
<?php

use App\Services\SupabaseService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[Title('Products')]
#[Layout('layouts.app')]
class extends Component
{
    public string $search = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|numeric|min:0')]
    public float $price = 0;

    #[Validate('required|integer|min:0')]
    public int $stock = 0;

    #[Computed]
    public function products(): array
    {
        $supabase = app(SupabaseService::class);

        $query = ['order' => 'created_at.desc'];

        if ($this->search !== '') {
            $query['name'] = "ilike.%{$this->search}%";
        }

        return $supabase->get('products', $query);
    }

    public function store(): void
    {
        $validated = $this->validate();

        $supabase = app(SupabaseService::class);
        $supabase->insert('products', $validated);

        $this->reset(['name', 'description', 'price', 'stock']);
        unset($this->products);

        session()->flash('success', 'Produk berhasil ditambahkan.');
    }

    public function deleteProduct(string $id): void
    {
        $supabase = app(SupabaseService::class);
        $supabase->delete('products', $id);
        unset($this->products);
    }
}; ?>

<div>
    <flux:heading size="xl">Products</flux:heading>
    <flux:subheading>Kelola data produk dari Supabase.</flux:subheading>

    <div class="mt-6 space-y-6">
        @if (session('success'))
            <flux:badge color="green">{{ session('success') }}</flux:badge>
        @endif

        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari produk..." icon="magnifying-glass" />

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama</flux:table.column>
                <flux:table.column>Harga</flux:table.column>
                <flux:table.column>Stok</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->products as $product)
                    <flux:table.row>
                        <flux:table.cell>{{ $product['name'] }}</flux:table.cell>
                        <flux:table.cell>Rp {{ number_format($product['price'], 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $product['stock'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="danger"
                                wire:click="deleteProduct('{{ $product['id'] }}')"
                                wire:confirm="Yakin hapus produk ini?">
                                Hapus
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">Tidak ada produk.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
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
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
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

```php
$response = Http::withHeaders($this->headers())
    ->withHeaders([
        'Range' => '0-9',
        'Prefer' => 'count=exact',
    ])
    ->get("{$this->baseUrl}/products");

$total = $response->header('Content-Range'); // "0-9/42"
$data = $response->json();
```

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
            '*/rest/v1/products' => Http::response([
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
