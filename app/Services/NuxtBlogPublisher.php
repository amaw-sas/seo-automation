<?php

namespace App\Services;

use App\Models\GeneratedPost;
use Illuminate\Support\Facades\Http;

class NuxtBlogPublisher
{
    /**
     * Full flow: upload images to Firebase via Nuxt endpoint, then sync the post.
     *
     * @return array{success: bool, filename: string, path: string, size: int}
     *
     * @throws \RuntimeException
     */
    public function sync(GeneratedPost $post, string $endpointUrl, string $apiKey): array
    {
        $endpointUrl = rtrim($endpointUrl, '/');

        // 1. Upload featured image → get Firebase Storage URL
        $featuredUrl = null;
        if ($post->featured_image_url) {
            $featuredUrl = $this->uploadImage(
                $post->featured_image_url,
                'featured',
                $post->title,
                $endpointUrl,
                $apiKey
            );
        }

        // 2. Upload inline images → get Firebase Storage URLs
        $inlineUrls = [];
        foreach ($post->inline_images ?? [] as $localUrl) {
            $inlineUrls[$localUrl] = $this->uploadImage(
                $localUrl,
                'content',
                $post->title,
                $endpointUrl,
                $apiKey
            );
        }

        // 3. Inject inline images into HTML content (replacing local URLs with Firebase URLs)
        $content = $this->injectInlineImages($post->content, $inlineUrls);

        // 4. Build WordPress REST API payload with Firebase image URLs
        $payload = [
            'id'       => $post->id,
            'title'    => ['rendered' => $post->title],
            'content'  => ['rendered' => $content],
            'excerpt'  => ['rendered' => '<p>' . ($post->excerpt ?: $post->meta_description) . '</p>'],
            'date'     => $post->created_at->toIso8601String(),
            'modified' => $post->updated_at->toIso8601String(),
            'slug'     => $post->slug,
            '_embedded' => [
                'wp:featuredmedia' => $featuredUrl
                    ? [['source_url' => $featuredUrl, 'alt_text' => $post->title]]
                    : [],
                'wp:term' => [
                    [['name' => 'Guías']],
                    [['name' => 'alquiler'], ['name' => 'colombia']],
                ],
            ],
        ];

        // 5. Sync post to Nuxt blog endpoint
        $response = Http::withHeaders(['x-api-key' => $apiKey])
            ->timeout(30)
            ->post("{$endpointUrl}/api/blog/wordpress-sync", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Nuxt sync failed [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json();
    }

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

    /**
     * Upload a local image file to Firebase Storage via the Nuxt upload-image endpoint.
     * Returns the public Firebase Storage URL.
     */
    private function uploadImage(
        string $localUrl,
        string $type,
        string $alt,
        string $endpointUrl,
        string $apiKey
    ): string {
        $localPath = $this->resolveLocalPath($localUrl);

        if (! file_exists($localPath)) {
            throw new \RuntimeException("Image file not found: {$localPath}");
        }

        $filename = basename($localPath);

        $response = Http::withHeaders(['x-api-key' => $apiKey])
            ->timeout(60)
            ->attach('file', file_get_contents($localPath), $filename)
            ->attach('type', $type)
            ->attach('alt', $alt)
            ->post("{$endpointUrl}/api/blog/upload-image");

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Image upload failed [{$response->status()}]: " . $response->body()
            );
        }

        $url = $response->json('url');

        if (! $url) {
            throw new \RuntimeException('Upload endpoint returned no URL: ' . $response->body());
        }

        return $url;
    }

    /**
     * Convert a public storage URL to an absolute filesystem path, regardless of host.
     * e.g. http://64.23.238.57/storage/generated/img_xxx.png
     *      → /path/to/project/storage/app/public/generated/img_xxx.png
     */
    private function resolveLocalPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH); // e.g. "/storage/generated/img_xxx.png"

        if ($path && str_starts_with($path, '/storage/')) {
            return storage_path('app/public') . substr($path, strlen('/storage'));
        }

        return $url;
    }

    /**
     * Inject inline images into HTML content, distributed after evenly-spaced H2 sections.
     * Uses the Firebase Storage URLs (already uploaded) instead of local ones.
     *
     * @param array<string,string> $urlMap  [localUrl => firebaseUrl]
     */
    private function injectInlineImages(string $content, array $urlMap): string
    {
        if (empty($urlMap)) {
            return $content;
        }

        $firebaseUrls = array_values($urlMap);

        // Find positions of all </h2> closing tags
        preg_match_all('/<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE);
        $h2Ends = array_column($matches[0], 1);

        if (empty($h2Ends)) {
            foreach ($firebaseUrls as $url) {
                $content .= "\n<figure><img src=\"{$url}\" alt=\"Imagen ilustrativa\" loading=\"lazy\"></figure>";
            }
            return $content;
        }

        // Distribute images after evenly-spaced H2s (skip the first one)
        $total = count($h2Ends);
        $step  = max(1, intdiv($total, count($firebaseUrls) + 1));

        $insertions = [];
        foreach ($firebaseUrls as $i => $url) {
            $idx = ($i + 1) * $step;
            if ($idx < $total) {
                $pos = $h2Ends[$idx] + strlen('</h2>');
                $insertions[$pos] = "\n<figure><img src=\"{$url}\" alt=\"Imagen ilustrativa\" loading=\"lazy\"></figure>\n";
            }
        }

        // Insert in reverse order to preserve offset validity
        krsort($insertions);
        foreach ($insertions as $pos => $img) {
            $content = substr($content, 0, $pos) . $img . substr($content, $pos);
        }

        return $content;
    }
}
