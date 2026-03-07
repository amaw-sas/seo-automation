<?php

namespace App\Services;

use App\Exceptions\ImageUploadException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\WordPressPublishException;
use App\Models\GeneratedPost;
use App\Models\WordPressSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressPublisher
{
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAYS = [0, 5, 15]; // segundos

    /**
     * Publica un GeneratedPost a un sitio WordPress.
     *
     * @param GeneratedPost $post
     * @param WordPressSite $site
     * @return PublishResult
     * @throws InvalidCredentialsException
     * @throws WordPressPublishException
     */
    public function publish(GeneratedPost $post, WordPressSite $site): PublishResult
    {
        $startTime = microtime(true);

        Log::info("Publishing post #{$post->id} to site #{$site->id} ({$site->site_url})");

        // 1. Validar credenciales
        if (!$this->validateCredentials($site)) {
            throw new InvalidCredentialsException(
                "No se pudo autenticar con {$site->site_url}. Verifica wp_username y wp_app_password."
            );
        }

        // 2. Validar pre-publicación
        PostValidator::validate($post);

        try {
            // 3. Upload imagen destacada
            $featuredImageData = $this->uploadImage($site, $post->featured_image_url);
            Log::info("Uploaded featured image → media_id: {$featuredImageData['id']}");

            // 4. Upload imágenes inline
            $imageMapping = [];
            $inlineImages = $post->inline_images ?? [];

            foreach ($inlineImages as $index => $imageUrl) {
                try {
                    $inlineImageData = $this->uploadImage($site, $imageUrl);
                    $imageMapping[$imageUrl] = $inlineImageData['source_url'];
                    Log::info("Uploaded inline image " . ($index + 1) . " → media_id: {$inlineImageData['id']}");
                } catch (ImageUploadException $e) {
                    // Log pero no fallar el post si falla una imagen inline
                    Log::warning("Failed to upload inline image {$imageUrl}: {$e->getMessage()}");
                }
            }

            // 5. Reemplazar URLs de imágenes en contenido
            $updatedContent = $this->replaceImageUrls($post->content, $imageMapping);
            Log::info("Replaced " . count($imageMapping) . " image URLs in content");

            // 6. Crear post en WordPress
            $wpPostData = [
                'title' => $post->title,
                'content' => $updatedContent,
                'excerpt' => $post->excerpt,
                'status' => 'publish', // Siempre publicar (decisión de diseño MVP)
                'categories' => [$site->default_category_id],
                'author' => $site->default_author_id,
                'featured_media' => $featuredImageData['id'],
            ];

            // Agregar meta description si WordPress tiene Yoast SEO
            if (!empty($post->meta_description)) {
                $wpPostData['meta'] = [
                    '_yoast_wpseo_metadesc' => $post->meta_description,
                ];
            }

            $wpResponse = $this->retryWithBackoff(function () use ($site, $wpPostData) {
                return $this->createWordPressPost($site, $wpPostData);
            });

            Log::info("Created WordPress post → post_id: {$wpResponse['id']}");

            // 7. Actualizar registro local
            $post->update([
                'status' => 'published',
                'target_wordpress_site_id' => $site->id,
                'wordpress_post_id' => $wpResponse['id'],
                'published_url' => $wpResponse['link'],
                'published_at' => now(),
            ]);

            // 8. Actualizar estadísticas del sitio
            $site->increment('total_posts_published');
            $site->update(['last_published_at' => now()]);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("Post #{$post->id} published successfully in {$duration}s");

            return new PublishResult(
                success: true,
                wordpressPostId: $wpResponse['id'],
                publishedUrl: $wpResponse['link'],
                duration: $duration,
                imagesUploaded: 1 + count($imageMapping)
            );
        } catch (\Exception $e) {
            Log::error("Failed to publish post #{$post->id}: {$e->getMessage()}");
            throw new WordPressPublishException(
                "Error al publicar post: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Valida que las credenciales de WordPress funcionen.
     *
     * @param WordPressSite $site
     * @return bool
     */
    private function validateCredentials(WordPressSite $site): bool
    {
        try {
            $response = Http::withBasicAuth(
                $site->wp_username,
                decrypt($site->wp_app_password)
            )->get("{$site->wp_rest_api_url}/wp-json/wp/v2/users/me");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Credential validation failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sube una imagen a WordPress Media Library.
     *
     * @param WordPressSite $site
     * @param string $localImageUrl
     * @return array ['id' => int, 'source_url' => string]
     * @throws ImageUploadException
     */
    private function uploadImage(WordPressSite $site, string $localImageUrl): array
    {
        // Convertir URL a path local
        $imagePath = str_replace(url('/'), '', $localImageUrl);
        $fullPath = public_path($imagePath);

        if (!file_exists($fullPath)) {
            throw new ImageUploadException("Imagen no encontrada: {$fullPath}");
        }

        $filename = basename($fullPath);
        $imageContent = file_get_contents($fullPath);

        if ($imageContent === false) {
            throw new ImageUploadException("No se pudo leer la imagen: {$fullPath}");
        }

        try {
            $response = Http::timeout(60)
                ->withBasicAuth(
                    $site->wp_username,
                    decrypt($site->wp_app_password)
                )
                ->attach('file', $imageContent, $filename)
                ->post("{$site->wp_rest_api_url}/wp-json/wp/v2/media");

            if (!$response->successful()) {
                throw new ImageUploadException(
                    "WordPress API error: " . $response->body()
                );
            }

            return [
                'id' => $response->json('id'),
                'source_url' => $response->json('source_url'),
            ];
        } catch (\Exception $e) {
            throw new ImageUploadException(
                "Error al subir imagen: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Reemplaza URLs de imágenes locales con URLs de WordPress.
     *
     * @param string $content
     * @param array $imageMapping ['local_url' => 'wp_url', ...]
     * @return string
     */
    private function replaceImageUrls(string $content, array $imageMapping): string
    {
        $updatedContent = $content;

        foreach ($imageMapping as $localUrl => $wpUrl) {
            $updatedContent = str_replace($localUrl, $wpUrl, $updatedContent);
        }

        return $updatedContent;
    }

    /**
     * Crea un post en WordPress vía REST API.
     *
     * @param WordPressSite $site
     * @param array $postData
     * @return array
     * @throws WordPressPublishException
     */
    private function createWordPressPost(WordPressSite $site, array $postData): array
    {
        $response = Http::timeout(30)
            ->withBasicAuth(
                $site->wp_username,
                decrypt($site->wp_app_password)
            )
            ->post("{$site->wp_rest_api_url}/wp-json/wp/v2/posts", $postData);

        if (!$response->successful()) {
            throw new WordPressPublishException(
                "WordPress API error: " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Ejecuta una operación con retry automático y exponential backoff.
     *
     * @param callable $operation
     * @param int $maxAttempts
     * @return mixed
     * @throws \Exception
     */
    private function retryWithBackoff(callable $operation, int $maxAttempts = self::MAX_RETRY_ATTEMPTS): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $maxAttempts - 1) {
                    $delay = self::RETRY_DELAYS[$attempt];
                    Log::warning(
                        "Operation failed (attempt " . ($attempt + 1) . "/{$maxAttempts}), " .
                        "retrying in {$delay}s: {$e->getMessage()}"
                    );
                    sleep($delay);
                }
            }
        }

        throw $lastException;
    }
}
