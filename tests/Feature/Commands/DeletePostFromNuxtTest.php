<?php

namespace Tests\Feature\Commands;

use App\Models\NuxtSite;
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
            $r->url() === 'https://mysite.com/api/blog/post/mi-slug' &&
            $r->header('x-api-key')[0] === 'secret-key'
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
            $r->url() === 'https://other.com/api/blog/post/otro-slug' &&
            $r->header('x-api-key')[0] === 'my-key'
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
