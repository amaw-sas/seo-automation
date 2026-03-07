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
