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

        if (! preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$|^[a-z0-9]$/', $slug)) {
            $this->error("Slug inválido: '{$slug}'. Solo se permiten letras minúsculas, números y guiones.");
            return self::FAILURE;
        }

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

            $this->line('<fg=green>✓ Post eliminado exitosamente.</>');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->newLine();
            $this->error('✗ Eliminación fallida:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
