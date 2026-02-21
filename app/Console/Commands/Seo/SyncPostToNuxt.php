<?php

namespace App\Console\Commands\Seo;

use App\Models\GeneratedPost;
use App\Models\NuxtSite;
use App\Services\NuxtBlogPublisher;
use Illuminate\Console\Command;

class SyncPostToNuxt extends Command
{
    protected $signature = 'seo:sync:nuxt
                            {post : ID del GeneratedPost a sincronizar}
                            {--site=  : ID del NuxtSite en la DB (carga url y api-key automáticamente)}
                            {--url=   : URL base del sitio Nuxt (sobreescribe site_url del DB si se usa --site)}
                            {--api-key= : API key del endpoint wordpress-sync (sobreescribe api_key del DB si se usa --site)}';

    protected $description = 'Sincroniza un GeneratedPost al endpoint /api/blog/wordpress-sync de un sitio Nuxt';

    public function handle(NuxtBlogPublisher $publisher): int
    {
        $postId  = $this->argument('post');
        $siteId  = $this->option('site');
        $url     = $this->option('url');
        $apiKey  = $this->option('api-key');

        $nuxtSite = null;

        if ($siteId) {
            $nuxtSite = NuxtSite::find($siteId);
            if (! $nuxtSite) {
                $this->error("NuxtSite #{$siteId} no encontrado");
                return self::FAILURE;
            }
            $url    ??= $nuxtSite->site_url;
            $apiKey ??= $nuxtSite->api_key;
        }

        $url    ??= 'http://localhost:3000';

        if (! $apiKey) {
            $this->error('Proporciona --site o --api-key');
            return self::FAILURE;
        }

        $post = GeneratedPost::find($postId);
        if (! $post) {
            $this->error("Post #{$postId} no encontrado");
            return self::FAILURE;
        }

        if ($nuxtSite) {
            $this->line("Sitio: {$nuxtSite->site_name} ({$nuxtSite->franchise})");
        }
        $this->info("Sincronizando post #{$post->id} → {$url}/api/blog/wordpress-sync");
        $this->line("  Título : {$post->title}");
        $this->line("  Slug   : {$post->slug}");
        $this->newLine();

        try {
            $result = $publisher->sync($post, $url, $apiKey);

            $this->line('<fg=green>✓ Post sincronizado exitosamente!</>');
            $this->newLine();
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['filename', $result['filename'] ?? '—'],
                    ['path',     $result['path']     ?? '—'],
                    ['size',     ($result['size'] ?? 0) . ' bytes'],
                    ['firebase', 'https://storage.googleapis.com/rentacar-403321.firebasestorage.app/' . ($result['path'] ?? '')],
                ]
            );

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->newLine();
            $this->error('✗ Sincronización fallida:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
