# Nuxt Delete Post Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Agregar comando Artisan `seo:nuxt:delete` que llama al endpoint `DELETE /api/blog/post/:slug` de un sitio Nuxt para eliminar un post.

**Architecture:** Se añade un método `delete()` al servicio existente `NuxtBlogPublisher` (mismo patrón que `sync()`), luego se crea el comando `DeletePostFromNuxt` con la misma interfaz de opciones que `SyncPostToNuxt` (`--site`, `--url`, `--api-key`).

**Tech Stack:** Laravel 11, PHP 8.2, `Illuminate\Support\Facades\Http`, PHPUnit/Pest via `php artisan test`

---

### Task 1: Agregar método `delete()` a `NuxtBlogPublisher`

**Blast radius:**
- Modify: `app/Services/NuxtBlogPublisher.php`
- Create: `tests/Unit/Services/NuxtBlogPublisherDeleteTest.php`

**Step 1: Escribir el test fallido**

Crear `tests/Unit/Services/NuxtBlogPublisherDeleteTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\NuxtBlogPublisher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NuxtBlogPublisherDeleteTest extends TestCase
{
    public function test_delete_sends_delete_request_to_correct_url(): void
    {
        Http::fake([
            'https://mysite.com/api/blog/post/mi-slug' => Http::response('', 200),
        ]);

        $publisher = new NuxtBlogPublisher();
        $publisher->delete('mi-slug', 'https://mysite.com', 'secret-key');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://mysite.com/api/blog/post/mi-slug'
                && $request->header('x-api-key')[0] === 'secret-key';
        });
    }

    public function test_delete_trims_trailing_slash_from_url(): void
    {
        Http::fake([
            'https://mysite.com/api/blog/post/mi-slug' => Http::response('', 200),
        ]);

        $publisher = new NuxtBlogPublisher();
        $publisher->delete('mi-slug', 'https://mysite.com/', 'secret-key');

        Http::assertSent(fn($r) => $r->url() === 'https://mysite.com/api/blog/post/mi-slug');
    }

    public function test_delete_throws_on_non_successful_response(): void
    {
        Http::fake([
            'https://mysite.com/api/blog/post/no-existe' => Http::response('Not found', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');

        $publisher = new NuxtBlogPublisher();
        $publisher->delete('no-existe', 'https://mysite.com', 'secret-key');
    }
}
```

**Step 2: Ejecutar el test para verificar que falla**

```bash
cd /home/pabloandi/proyectos/amaw/seo-automation/seo-automation
php artisan test tests/Unit/Services/NuxtBlogPublisherDeleteTest.php
```

Esperado: FAIL — `Call to undefined method App\Services\NuxtBlogPublisher::delete()`

**Step 3: Implementar el método `delete()` en `NuxtBlogPublisher`**

Agregar al final de la clase (antes del último `}`), después del método `sync()`:

```php
/**
 * Delete a post from the Nuxt blog by slug.
 *
 * @throws \RuntimeException
 */
public function delete(string $slug, string $endpointUrl, string $apiKey): void
{
    $endpointUrl = rtrim($endpointUrl, '/');

    $response = Http::withHeaders(['x-api-key' => $apiKey])
        ->timeout(30)
        ->delete("{$endpointUrl}/api/blog/post/{$slug}");

    if (! $response->successful()) {
        throw new \RuntimeException(
            "Nuxt delete failed [{$response->status()}]: " . $response->body()
        );
    }
}
```

**Step 4: Ejecutar el test para verificar que pasa**

```bash
php artisan test tests/Unit/Services/NuxtBlogPublisherDeleteTest.php
```

Esperado: 3 tests PASS

**Step 5: Commit**

```bash
git add app/Services/NuxtBlogPublisher.php tests/Unit/Services/NuxtBlogPublisherDeleteTest.php
git commit -m "feat: add delete() method to NuxtBlogPublisher"
```

---

### Task 2: Crear el comando `seo:nuxt:delete`

**Blast radius:**
- Create: `app/Console/Commands/Seo/DeletePostFromNuxt.php`
- Create: `tests/Feature/Commands/DeletePostFromNuxtTest.php`

**Step 1: Escribir el test fallido**

Crear `tests/Feature/Commands/DeletePostFromNuxtTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\NuxtSite;
use App\Services\NuxtBlogPublisher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeletePostFromNuxtTest extends TestCase
{
    public function test_deletes_post_using_site_id(): void
    {
        $site = NuxtSite::factory()->create([
            'site_url' => 'https://mysite.com',
            'api_key'  => 'secret-key',
        ]);

        Http::fake([
            'https://mysite.com/api/blog/post/mi-slug' => Http::response('', 200),
        ]);

        $this->artisan('seo:nuxt:delete', [
            'slug'   => 'mi-slug',
            '--site' => $site->id,
        ])->assertExitCode(0);

        Http::assertSent(fn($r) =>
            $r->method() === 'DELETE' &&
            $r->url() === 'https://mysite.com/api/blog/post/mi-slug'
        );
    }

    public function test_deletes_post_using_url_and_api_key_directly(): void
    {
        Http::fake([
            'https://other.com/api/blog/post/otro-slug' => Http::response('', 204),
        ]);

        $this->artisan('seo:nuxt:delete', [
            'slug'      => 'otro-slug',
            '--url'     => 'https://other.com',
            '--api-key' => 'my-key',
        ])->assertExitCode(0);

        Http::assertSent(fn($r) =>
            $r->url() === 'https://other.com/api/blog/post/otro-slug'
        );
    }

    public function test_fails_when_no_auth_provided(): void
    {
        $this->artisan('seo:nuxt:delete', ['slug' => 'mi-slug'])
            ->assertExitCode(1);
    }

    public function test_fails_when_site_not_found(): void
    {
        $this->artisan('seo:nuxt:delete', [
            'slug'   => 'mi-slug',
            '--site' => 9999,
        ])->assertExitCode(1);
    }

    public function test_shows_error_on_failed_delete(): void
    {
        Http::fake([
            'https://mysite.com/api/blog/post/mi-slug' => Http::response('Forbidden', 403),
        ]);

        $this->artisan('seo:nuxt:delete', [
            'slug'      => 'mi-slug',
            '--url'     => 'https://mysite.com',
            '--api-key' => 'bad-key',
        ])->assertExitCode(1);
    }
}
```

**Step 2: Ejecutar los tests para verificar que fallan**

```bash
php artisan test tests/Feature/Commands/DeletePostFromNuxtTest.php
```

Esperado: FAIL — `Command seo:nuxt:delete not found`

**Step 3: Crear el comando `DeletePostFromNuxt`**

Crear `app/Console/Commands/Seo/DeletePostFromNuxt.php`:

```php
<?php

namespace App\Console\Commands\Seo;

use App\Models\NuxtSite;
use App\Services\NuxtBlogPublisher;
use Illuminate\Console\Command;

class DeletePostFromNuxt extends Command
{
    protected $signature = 'seo:nuxt:delete
                            {slug            : Slug del post a eliminar}
                            {--site=         : ID del NuxtSite en la DB}
                            {--url=          : URL base del sitio Nuxt (sobreescribe site_url si se usa --site)}
                            {--api-key=      : API key del endpoint (sobreescribe api_key si se usa --site)}';

    protected $description = 'Elimina un post del endpoint /api/blog/post/:slug de un sitio Nuxt';

    public function handle(NuxtBlogPublisher $publisher): int
    {
        $slug   = $this->argument('slug');
        $siteId = $this->option('site');
        $url    = $this->option('url');
        $apiKey = $this->option('api-key');

        if ($siteId) {
            $nuxtSite = NuxtSite::find($siteId);
            if (! $nuxtSite) {
                $this->error("NuxtSite #{$siteId} no encontrado");
                return self::FAILURE;
            }
            $url    ??= $nuxtSite->site_url;
            $apiKey ??= $nuxtSite->api_key;

            $this->line("Sitio: {$nuxtSite->site_name} ({$nuxtSite->franchise})");
        }

        $url ??= 'http://localhost:3000';

        if (! $apiKey) {
            $this->error('Proporciona --site o --api-key');
            return self::FAILURE;
        }

        $this->info("Eliminando post '{$slug}' de {$url}/api/blog/post/{$slug}");

        try {
            $publisher->delete($slug, $url, $apiKey);

            $this->line('<fg=green>Post eliminado exitosamente.</>');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->newLine();
            $this->error('Eliminacion fallida:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
```

**Step 4: Verificar que Laravel auto-descubre el comando**

Laravel descubre automáticamente comandos en `app/Console/Commands/`. Verificar:

```bash
php artisan list seo:nuxt
```

Esperado: aparece `seo:nuxt:delete` en la lista.

**Step 5: Ejecutar los tests para verificar que pasan**

```bash
php artisan test tests/Feature/Commands/DeletePostFromNuxtTest.php
```

Nota: Si los tests de Feature requieren una factory para `NuxtSite`, revisar si existe en `database/factories/NuxtSiteFactory.php`. Si no existe, crear:

```php
<?php

namespace Database\Factories;

use App\Models\NuxtSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class NuxtSiteFactory extends Factory
{
    protected $model = NuxtSite::class;

    public function definition(): array
    {
        return [
            'site_name' => $this->faker->company(),
            'franchise' => $this->faker->slug(2),
            'site_url'  => $this->faker->url(),
            'api_key'   => $this->faker->uuid(),
            'is_active' => true,
        ];
    }
}
```

Y agregar `use HasFactory;` al modelo `NuxtSite` si no lo tiene.

Esperado: 5 tests PASS

**Step 6: Correr toda la suite para verificar que no hay regresiones**

```bash
php artisan test
```

Esperado: todos los tests pasan.

**Step 7: Commit**

```bash
git add app/Console/Commands/Seo/DeletePostFromNuxt.php \
        tests/Feature/Commands/DeletePostFromNuxtTest.php
git commit -m "feat: add seo:nuxt:delete command to delete posts from Nuxt sites"
```

---

## Uso final

```bash
# Via site ID (recomendado)
php artisan seo:nuxt:delete mi-slug-del-post --site=2

# Via URL directa
php artisan seo:nuxt:delete mi-slug-del-post --url=https://alquicarros.com --api-key=xxx
```
