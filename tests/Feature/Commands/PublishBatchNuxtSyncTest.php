<?php

namespace Tests\Feature\Commands;

use App\Models\Domain;
use App\Models\GeneratedPost;
use App\Models\NuxtSite;
use App\Models\WordPressSite;
use App\Services\NuxtBlogPublisher;
use App\Services\PublishResult;
use App\Services\WordPressPublisher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class PublishBatchNuxtSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_nuxt_flag_syncs_to_linked_nuxt_site(): void
    {
        $domain   = Domain::factory()->create();
        $wpSite   = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        $nuxtSite = NuxtSite::factory()->create([
            'domain_id' => $domain->id,
            'site_url'  => 'https://nuxt.com',
            'api_key'   => 'nuxt-key',
            'is_active' => true,
        ]);
        $post = GeneratedPost::factory()->create([
            'status'             => 'draft',
            'quality_score'      => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = new PublishResult(
            success: true,
            wordpressPostId: 99,
            publishedUrl: 'https://wp.com/post-slug',
            duration: 1.0,
            imagesUploaded: 1,
        );
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldReceive('sync')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof GeneratedPost && $arg->id === $post->id), 'https://nuxt.com', 'nuxt-key')
            ->andReturn(['filename' => 'post.md', 'path' => 'posts/post.md', 'size' => 100]);
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        $this->artisan('seo:publish:batch', [
            '--limit'       => 10,
            '--min-quality' => 70,
            '--site'        => $wpSite->id,
            '--sync-nuxt'   => true,
        ])->assertExitCode(0);
    }

    public function test_sync_nuxt_skipped_when_no_nuxt_site_linked(): void
    {
        $domain = Domain::factory()->create();
        $wpSite = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        // No NuxtSite para este domain

        $post = GeneratedPost::factory()->create([
            'status'             => 'draft',
            'quality_score'      => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = new PublishResult(
            success: true,
            wordpressPostId: 99,
            publishedUrl: 'https://wp.com/post',
            duration: 1.0,
            imagesUploaded: 1,
        );
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
            '--sync-nuxt'   => true,
        ])->assertExitCode(0);
    }

    public function test_nuxt_sync_failure_does_not_abort_batch(): void
    {
        $domain   = Domain::factory()->create();
        $wpSite   = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        $nuxtSite = NuxtSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);

        $post = GeneratedPost::factory()->create([
            'status'             => 'draft',
            'quality_score'      => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = new PublishResult(
            success: true,
            wordpressPostId: 99,
            publishedUrl: 'https://wp.com/post',
            duration: 1.0,
            imagesUploaded: 1,
        );
        $wpPublisher = Mockery::mock(WordPressPublisher::class);
        $wpPublisher->shouldReceive('publish')->once()->andReturn($wpResult);
        $this->app->instance(WordPressPublisher::class, $wpPublisher);

        $nuxtPublisher = Mockery::mock(NuxtBlogPublisher::class);
        $nuxtPublisher->shouldReceive('sync')
            ->once()
            ->andThrow(new \RuntimeException('Nuxt unreachable'));
        $this->app->instance(NuxtBlogPublisher::class, $nuxtPublisher);

        $this->artisan('seo:publish:batch', [
            '--limit'       => 10,
            '--min-quality' => 70,
            '--site'        => $wpSite->id,
            '--sync-nuxt'   => true,
        ])->assertExitCode(0);
    }

    public function test_without_sync_nuxt_flag_nuxt_is_not_called(): void
    {
        $domain   = Domain::factory()->create();
        $wpSite   = WordPressSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);
        NuxtSite::factory()->create(['domain_id' => $domain->id, 'is_active' => true]);

        $post = GeneratedPost::factory()->create([
            'status'             => 'draft',
            'quality_score'      => 80,
            'featured_image_url' => 'http://localhost/storage/generated/img.png',
        ]);

        $wpResult = new PublishResult(
            success: true,
            wordpressPostId: 99,
            publishedUrl: 'https://wp.com/post',
            duration: 1.0,
            imagesUploaded: 1,
        );
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
            // Sin --sync-nuxt
        ])->assertExitCode(0);
    }
}
