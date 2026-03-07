# Publish Batch Nuxt Sync Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extender `seo:publish:batch` con flag `--sync-nuxt` para que, tras publicar en WordPress, sincronice automáticamente al sitio Nuxt vinculado vía `domain_id`.

**Architecture:** Se agrega el flag `--sync-nuxt` al comando existente `PublishBatch`. Después de cada publicación WP exitosa, se busca un `NuxtSite` activo con el mismo `domain_id` del `WordPressSite` y se llama `NuxtBlogPublisher::sync()`. Si no existe Nuxt site vinculado, se omite silenciosamente. El schedule en `routes/console.php` se actualiza para incluir el flag.

**Tech Stack:** Laravel 11, PHP 8.2, `NuxtBlogPublisher` (ya existe), `NuxtSite` model (ya existe), PHPUnit via `php artisan test`

---

### Relación domain_id (contexto crucial)

```
wordpress_sites.domain_id ──┐
                             ├── domains.id
nuxt_sites.domain_id ───────┘
```

Consulta para encontrar el Nuxt site vinculado a un WordPressSite:
```php
NuxtSite::where('domain_id', $wpSite->domain_id)
         ->where('is_active', true)
         ->first();
```

---

### Task 1: Agregar `--sync-nuxt` a `PublishBatch` + tests

**Blast radius:**
- Modify: `app/Console/Commands/Seo/PublishBatch.php`
- Modify: `routes/console.php` (1 línea)
- Create: `tests/Feature/Commands/PublishBatchNuxtSyncTest.php`

---

**Step 1: Escribir el test fallido**

Crear `tests/Feature/Commands/PublishBatchNuxtSyncTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Domain;
use App\Models\GeneratedPost;
use App\Models\NuxtSite;
use App\Models\WordPressSite;
use App\Services\NuxtBlogPublisher;
use App\Services\WordPressPublisher;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PublishBatchNuxtSyncTest extends TestCase
{
    public function test_sync_nuxt_flag_syncs_to_linked_nuxt_site(): void
    {
        // Arrange: domain vincula WP y Nuxt
        $domain = Domain::factory()->create();

        $wpSite = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        $nuxtSite = NuxtSite::factory()->create([
            'domain_id' => $domain->id,
            'site_url'  => 'https://nuxt.com',
            'api_key'   => 'nuxt-key',
            'is_active' => true,
        ]);

        $post = GeneratedPost::factory()->create([
            'status'        => 'draft',
            'quality_score' => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        // Mock WordPressPublisher para no hacer HTTP real
        $wpResult = (object)[
            'wordpressPostId' => 99,
            'publishedUrl'    => 'https://wp.com/post-slug',
            'imagesUploaded'  => 1,
            'duration'        => 1.0,
        ];
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        // Mock NuxtBlogPublisher para capturar la llamada
        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldReceive('sync')
            ->once()
            ->with($post, 'https://nuxt.com', 'nuxt-key')
            ->andReturn(['filename' => 'post.md', 'path' => 'posts/post.md', 'size' => 100]);
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        // Act
        $this->artisan('seo:publish:batch', [
            '--limit'      => 10,
            '--min-quality'=> 70,
            '--site'       => $wpSite->id,
            '--sync-nuxt'  => true,
        ])->assertExitCode(0);
    }

    public function test_sync_nuxt_skipped_when_no_nuxt_site_linked(): void
    {
        $domain = Domain::factory()->create();
        $wpSite = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        // No NuxtSite creado para este domain

        $post = GeneratedPost::factory()->create([
            'status'        => 'draft',
            'quality_score' => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = (object)[
            'wordpressPostId' => 99,
            'publishedUrl'    => 'https://wp.com/post',
            'imagesUploaded'  => 1,
            'duration'        => 1.0,
        ];
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        // NuxtBlogPublisher no debe ser llamado
        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldNotReceive('sync');
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        $this->artisan('seo:publish:batch', [
            '--limit'      => 10,
            '--min-quality'=> 70,
            '--site'       => $wpSite->id,
            '--sync-nuxt'  => true,
        ])->assertExitCode(0);
    }

    public function test_nuxt_sync_failure_does_not_abort_batch(): void
    {
        $domain = Domain::factory()->create();
        $wpSite = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        $nuxtSite = NuxtSite::factory()->create([
            'domain_id' => $domain->id,
            'site_url'  => 'https://nuxt.com',
            'api_key'   => 'nuxt-key',
            'is_active' => true,
        ]);

        $post = GeneratedPost::factory()->create([
            'status'        => 'draft',
            'quality_score' => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = (object)[
            'wordpressPostId' => 99,
            'publishedUrl'    => 'https://wp.com/post',
            'imagesUploaded'  => 1,
            'duration'        => 1.0,
        ];
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        // NuxtBlogPublisher lanza excepción
        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldReceive('sync')
            ->once()
            ->andThrow(new \RuntimeException('Nuxt unreachable'));
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        // El batch debe seguir completándose (exit 0) a pesar del error Nuxt
        $this->artisan('seo:publish:batch', [
            '--limit'      => 10,
            '--min-quality'=> 70,
            '--site'       => $wpSite->id,
            '--sync-nuxt'  => true,
        ])->assertExitCode(0);
    }

    public function test_without_sync_nuxt_flag_nuxt_is_not_called(): void
    {
        $domain = Domain::factory()->create();
        $wpSite = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        NuxtSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);

        $post = GeneratedPost::factory()->create([
            'status'        => 'draft',
            'quality_score' => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = (object)[
            'wordpressPostId' => 99,
            'publishedUrl'    => 'https://wp.com/post',
            'imagesUploaded'  => 1,
            'duration'        => 1.0,
        ];
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldNotReceive('sync');
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        $this->artisan('seo:publish:batch', [
            '--limit'       => 10,
            '--min-quality' => 70,
            '--site'        => $wpSite->id,
            // --sync-nuxt NO pasado
        ])->assertExitCode(0);
    }
}
```

**Step 2: Verificar si existen factories necesarias**

```bash
ls /home/pabloandi/proyectos/amaw/seo-automation/seo-automation/database/factories/
```

Se necesitan factories para: `Domain`, `WordPressSite`, `GeneratedPost`. Si alguna no existe, crearla.

**Factory para `Domain`** (si no existe `DomainFactory.php`):
```php
<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain'    => $this->faker->domainName(),
            'is_own'    => true,
            'is_active' => true,
        ];
    }
}
```

**Factory para `WordPressSite`** (si no existe `WordPressSiteFactory.php`):
```php
<?php

namespace Database\Factories;

use App\Models\WordPressSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordPressSiteFactory extends Factory
{
    protected $model = WordPressSite::class;

    public function definition(): array
    {
        return [
            'site_name'      => $this->faker->company(),
            'site_url'       => 'https://' . $this->faker->domainName(),
            'wp_rest_api_url'=> 'https://' . $this->faker->domainName() . '/wp-json/wp/v2',
            'wp_username'    => $this->faker->userName(),
            'wp_app_password'=> $this->faker->uuid(),
            'is_active'      => true,
            'auto_publish'   => false,
            'require_review' => false,
        ];
    }
}
```

**Factory para `GeneratedPost`** (si no existe `GeneratedPostFactory.php`):
```php
<?php

namespace Database\Factories;

use App\Models\GeneratedPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeneratedPostFactory extends Factory
{
    protected $model = GeneratedPost::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        return [
            'title'            => $title,
            'slug'             => Str::slug($title),
            'content'          => '<p>' . $this->faker->paragraphs(5, true) . '</p>',
            'excerpt'          => $this->faker->sentence(20),
            'meta_description' => $this->faker->sentence(15),
            'status'           => 'draft',
            'quality_score'    => 80,
            'word_count'       => 500,
            'llm_provider'     => 'anthropic',
            'llm_cost_usd'     => 0.01,
            'image_generation_cost_usd' => 0.0,
        ];
    }
}
```

Si algún modelo le falta `use HasFactory;`, agregarlo.

**Step 3: Ejecutar el test para verificar que falla**

```bash
cd /home/pabloandi/proyectos/amaw/seo-automation/seo-automation && php artisan test tests/Feature/Commands/PublishBatchNuxtSyncTest.php
```

Esperado: FAIL — `--sync-nuxt option does not exist` o similar.

**Step 4: Modificar `app/Console/Commands/Seo/PublishBatch.php`**

**4a. Agregar `--sync-nuxt` a la signature** (después de `--site=`):
```php
{--sync-nuxt : Si se especifica, sincroniza cada post publicado al sitio Nuxt vinculado por domain_id}';
```

**4b. Agregar `NuxtBlogPublisher` y `NuxtSite` a los imports** (al inicio del archivo):
```php
use App\Models\NuxtSite;
use App\Services\NuxtBlogPublisher;
```

**4c. Agregar el parámetro `NuxtBlogPublisher` al método `handle()`**:
```php
public function handle(WordPressPublisher $publisher, NuxtBlogPublisher $nuxtPublisher): int
```

**4d. Dentro del loop `foreach ($posts as $index => $post)`, después del bloque `try` exitoso** (después de la línea `$this->successCount++; $this->totalDuration += $result->duration;`), agregar:

```php
// Sincronizar a Nuxt si el flag está activo y el sitio WP tiene domain vinculado
if ($this->option('sync-nuxt') && $site->domain_id) {
    $nuxtSite = NuxtSite::where('domain_id', $site->domain_id)
        ->where('is_active', true)
        ->first();

    if ($nuxtSite) {
        try {
            $nuxtPublisher->sync($post, $nuxtSite->site_url, $nuxtSite->api_key);
            $this->info("      ✓ Synced to Nuxt ({$nuxtSite->site_name})");
        } catch (\RuntimeException $e) {
            $this->warn("      ⚠ Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
            Log::warning("Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
        }
    }
}
```

**Step 5: Ejecutar los tests para verificar que pasan**

```bash
cd /home/pabloandi/proyectos/amaw/seo-automation/seo-automation && php artisan test tests/Feature/Commands/PublishBatchNuxtSyncTest.php
```

Esperado: 4 tests PASS.

**Step 6: Correr suite completa para verificar no hay regresiones**

```bash
cd /home/pabloandi/proyectos/amaw/seo-automation/seo-automation && php artisan test
```

Esperado: todos los tests pasan.

**Step 7: Actualizar el schedule en `routes/console.php`**

Cambiar:
```php
Schedule::command('seo:publish:batch --limit=2 --min-quality=70')
```

Por:
```php
Schedule::command('seo:publish:batch --limit=2 --min-quality=70 --sync-nuxt')
```

**Step 8: Commit**

```bash
git add app/Console/Commands/Seo/PublishBatch.php \
        routes/console.php \
        tests/Feature/Commands/PublishBatchNuxtSyncTest.php \
        database/factories/  # Solo los nuevos que se hayan creado
git commit -m "feat(seo): extend publish:batch with --sync-nuxt flag for automatic Nuxt sync"
```

---

## Comportamiento final

```bash
# Con sync a Nuxt (nuevo comportamiento del schedule)
php artisan seo:publish:batch --limit=2 --min-quality=70 --sync-nuxt

# Sin sync a Nuxt (comportamiento anterior, sin cambios)
php artisan seo:publish:batch --limit=2 --min-quality=70
```

**Flujo por post cuando `--sync-nuxt` está activo:**
1. Publica en WordPress → `status = published`
2. Busca `NuxtSite` con mismo `domain_id` del `WordPressSite`
3. Si existe → llama `NuxtBlogPublisher::sync()` → log de éxito
4. Si no existe → omite silenciosamente
5. Si falla → warn en output + `Log::warning()` → **no aborta el batch**
