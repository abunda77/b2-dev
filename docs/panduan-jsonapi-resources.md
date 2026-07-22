# Panduan JSON:API Resources di Laravel 13

## Ringkasan

Laravel 13 memperkenalkan **JSON:API Resources** sebagai fitur first-party. Fitur ini memungkinkan Anda menghasilkan respons API yang **compliant dengan spesifikasi JSON:API** secara standar, termasuk resource serialization, relationship inclusion, sparse fieldsets, links, dan header respons yang sesuai.

JSON:API Resources tersedia di namespace `Illuminate\Http\JsonApiResource` dan terintegrasi langsung dengan Eloquent ORM, query builder, dan routing Laravel.

---

## Referensi Teknis

### Context7

- `/laravel/docs/__branch__13.x`

### Topik utama dari referensi

- JSON:API Resources pertama kali hadir sebagai fitur first-party di Laravel 13
- Namespace: `Illuminate\Http\JsonApiResource`
- Mendukung resource object serialization sesuai spesifikasi JSON:API
- Relationship inclusion (`?include=` query parameter)
- Sparse fieldsets (`?fields=` query parameter)
- Links & pagination metadata otomatis
- Header `Content-Type: application/vnd.api+json` otomatis

---

## Ringkas

JSON:API Resources mengubah model Eloquent menjadi respons yang mematuhi spesifikasi [JSON:API v1.0](https://jsonapi.org/). Setiap respons memiliki struktur standar:

```json
{
  "data": {
    "type": "users",
    "id": "1",
    "attributes": {
      "name": "John Doe",
      "email": "john@example.com"
    },
    "relationships": {
      "posts": {
        "data": [
          { "type": "posts", "id": "10" }
        ]
      }
    },
    "links": {
      "self": "https://api.example.com/users/1"
    }
  },
  "included": [
    {
      "type": "posts",
      "id": "10",
      "attributes": {
        "title": "My First Post"
      }
    }
  ]
}
```

---

## 1. Membuat JSON:API Resource

### 1.1 Menggunakan Artisan

```bash
php artisan make:resource UserJsonApiResource --json-api
```

Atau secara manual:

```bash
php artisan make:resource UserJsonApiResource
```

### 1.2 Struktur Resource Dasar

**`app/Http/Resources/UserJsonApiResource.php`**:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonApiResource;
use Illuminate\Http\Request;

class UserJsonApiResource extends JsonApiResource
{
    /**
     * Tipe resource (coincides dengan JSON:API spec).
     */
    public static $type = 'users';

    /**
     * Transform resource ke dalam JSON:API format.
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get resource relationships.
     */
    public function toRelationships(Request $request): array
    {
        return [
            'posts' => PostJsonApiResource::collection($this->whenLoaded('posts')),
        ];
    }

    /**
     * Get resource links.
     */
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.users.show', $this->id),
        ];
    }
}
```

### 1.3 Perbedaan dengan Eloquent Resource Biasa

| Fitur | Eloquent Resource | JSON:API Resource |
|-------|-------------------|-------------------|
| **Namespace** | `Illuminate\Http\Resources\Json\JsonResource` | `Illuminate\Http\JsonApiResource` |
| **Format output** | Bebas (kustom) | Spesifikasi JSON:API |
| **Method utama** | `toArray()` | `toAttributes()`, `toRelationships()`, `toLinks()` |
| **Header otomatis** | Tidak | `application/vnd.api+json` |
| **Relationships** | Manual via `whenLoaded` | Native via `toRelationships()` |
| **Sparse fieldsets** | Tidak ada | Built-in support |

---

## 2. Menggunakan Resource di Controller

### 2.1 Single Resource

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserJsonApiResource;
use App\Models\User;

class UserController extends Controller
{
    public function show(User $user)
    {
        return new UserJsonApiResource($user);
    }
}
```

### 2.2 Collection Resource

```php
public function index()
{
    $users = User::paginate(20);

    return UserJsonApiResource::collection($users);
}
```

### 2.3 Response dengan Relationship

```php
public function show(User $user)
{
    // Eager load relationship jika ada query parameter include
    $user->loadMissing(['posts', 'profile']);

    return new UserJsonApiResource($user);
}
```

---

## 3. Fitur JSON:API Resources

### 3.1 Relationship Inclusion (`?include=`)

Klien bisa meminta relationship menggunakan query parameter `include`:

```http
GET /api/users/1?include=posts
```

Implementasi di resource:

```php
public function toRelationships(Request $request): array
{
    return [
        'posts' => PostJsonApiResource::collection(
            $this->whenLoaded('posts')
        ),
        'profile' => ProfileJsonApiResource::make(
            $this->whenLoaded('profile')
        ),
    ];
}
```

**Respons:**

```json
{
  "data": {
    "type": "users",
    "id": "1",
    "attributes": { ... },
    "relationships": {
      "posts": {
        "data": [
          { "type": "posts", "id": "10" },
          { "type": "posts", "id": "11" }
        ]
      }
    }
  },
  "included": [
    {
      "type": "posts",
      "id": "10",
      "attributes": { "title": "First Post" }
    },
    {
      "type": "posts",
      "id": "11",
      "attributes": { "title": "Second Post" }
    }
  ]
}
```

### 3.2 Sparse Fieldsets (`?fields=`)

Klien bisa meminta field spesifik:

```http
GET /api/users/1?fields[users]=name,email&fields[posts]=title
```

Implementasi otomatis handled oleh Laravel — resource hanya akan mengembalikan field yang diminta.

### 3.3 Pagination

Pagination otomatis menghasilkan `links` dan `meta` sesuai JSON:API:

```php
public function index()
{
    return UserJsonApiResource::collection(
        User::paginate(20)
    );
}
```

**Respons:**

```json
{
  "data": [ ... ],
  "links": {
    "first": "https://api.example.com/api/users?page=1",
    "last": "https://api.example.com/api/users?page=5",
    "prev": null,
    "next": "https://api.example.com/api/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "https://api.example.com/api/users",
    "per_page": 20,
    "to": 20,
    "total": 100
  }
}
```

### 3.4 Links

Override method `toLinks()` untuk menambahkan custom links:

```php
public function toLinks(Request $request): array
{
    return [
        'self' => route('api.users.show', $this->id),
        'avatar' => route('api.users.avatar', $this->id),
        'related' => route('api.users.posts', $this->id),
    ];
}
```

### 3.5 Meta

Tambahkan metadata kustom di luar struktur JSON:API standar:

```php
public function with(Request $request): array
{
    return [
        'meta' => [
            'api_version' => '1.0',
            'generated_at' => now()->toIso8601String(),
        ],
    ];
}
```

---

## 4. Contoh Lengkap: Blog API

### 4.1 Model & Relationship

**`app/Models/Post.php`**:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'body',
        'status',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

### 4.2 JSON:API Resource untuk Post

**`app/Http/Resources/PostJsonApiResource.php`**:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonApiResource;
use Illuminate\Http\Request;

class PostJsonApiResource extends JsonApiResource
{
    public static $type = 'posts';

    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'author' => UserJsonApiResource::make($this->whenLoaded('user')),
            'comments' => CommentJsonApiResource::collection($this->whenLoaded('comments')),
        ];
    }

    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.posts.show', $this->slug),
        ];
    }
}
```

### 4.3 Controller

**`app/Http/Controllers/Api/PostController.php`**:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostJsonApiResource;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('sort'), fn ($q) => $q->orderBy($request->sort, 'desc'))
            ->paginate($request->input('per_page', 20));

        return PostJsonApiResource::collection($posts);
    }

    public function show(Request $request, Post $post)
    {
        $post->loadMissing(['user', 'comments']);

        return new PostJsonApiResource($post);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ]);

        $post = $request->user()->posts()->create($validated);

        return new PostJsonApiResource($post->load('user'));
    }
}
```

### 4.4 Routes

**`routes/api.php`**:

```php
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::apiResource('posts', PostController::class);
```

---

## 5. Error Respons JSON:API

Laravel 13 secara otomatis memformat error ke format JSON:API. Namun, Anda bisa mengustomisasi:

**`app/Exceptions/Handler.php`** (atau `bootstrap/app.php` untuk Laravel 13):

```php
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// Error JSON:API format otomatis:
{
  "errors": [
    {
      "status": "422",
      "title": "Validation Error",
      "detail": "The title field is required.",
      "source": {
        "pointer": "/data/attributes/title"
      }
    }
  ]
}
```

---

## 6. Testing JSON:API Resources

```bash
php artisan make:test --phpunit PostJsonApiResourceTest
```

**`tests/Feature/PostJsonApiResourceTest.php`**:

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostJsonApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_resource_returns_json_api_format(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/posts/{$post->slug}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'attributes' => [
                        'title',
                        'slug',
                        'body',
                        'status',
                    ],
                    'links' => [
                        'self',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'type' => 'posts',
                    'attributes' => [
                        'title' => $post->title,
                    ],
                ],
            ]);
    }

    public function test_post_collection_returns_paginated_json_api(): void
    {
        Post::factory()->count(25)->create();

        $response = $this->getJson('/api/posts?page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['type', 'id', 'attributes']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_post_includes_relationships_when_requested(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/posts/' . $post->slug . '?include=author');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'relationships' => [
                        'author' => ['data' => ['type', 'id']],
                    ],
                ],
            ]);
    }
}
```

Jalankan test:

```bash
php artisan test --compact --filter=PostJsonApiResourceTest
```

---

## 7. Best Practices

1. **Gunakan `$type` yang konsisten** — tipe resource harus konsisten dengan naming convention (singular, lowercase).
2. **Eager load relationships** — gunakan `load()` atau `loadMissing()` sebelum return resource untuk menghindari N+1 queries.
3. **Gunakan `whenLoaded()`** — agar relationships hanya di-include saat memang di-load.
4. **Sparse fieldsets sudah handled otomatis** — tidak perlu implementasi manual, Laravel akan filter field berdasarkan query parameter `fields[type]=field1,field2`.
5. **Pagination otomatis** — gunakan `paginate()` bukan `get()` untuk mendapatkan `links` dan `meta` otomatis.
6. **Content-Type otomatis** — Laravel 13 akan mengirim header `application/vnd.api+json` untuk JSON:API Resources tanpa perlu manual.
7. **Versioning API** — pertimbangkan API versioning (`/api/v1/posts`) untuk backward compatibility.
8. **Error format standar** — Laravel otomatis memformat error ke JSON:API format.

---

## 8. Perbandingan: Eloquent Resource vs JSON:API Resource

| Aspek | Eloquent Resource | JSON:API Resource |
|-------|-------------------|-------------------|
| **Struktur** | Bebas/kustom | Spesifik (JSON:API spec) |
| **Relationship** | `whenLoaded()` manual | `toRelationships()` native |
| **Links** | `with()` manual | `toLinks()` built-in |
| **Pagination** | Manual `links` & `meta` | Otomatis |
| **Sparse Fieldsets** | Tidak ada | Built-in (`?fields=`) |
| **Content-Type** | `application/json` | `application/vnd.api+json` |
| **Kasus Penggunaan** | API internal, frontend sendiri | API publik, standar industri |

---

## 9. Referensi

- [Laravel 13 Eloquent Resources - JSON:API](https://laravel.com/docs/13.x/eloquent-resources#jsonapi-resources)
- [JSON:API Specification v1.0](https://jsonapi.org/)
- [Laravel 13 Release Notes](https://laravel.com/docs/13.x/releases)

---

Panduan ini disusun untuk project Laravel 13 `b2-dev`.
