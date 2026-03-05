<?php

namespace App\Console\Commands\Seo;

use App\Models\NuxtSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AddNuxtSite extends Command
{
    protected $signature = 'seo:site:add:nuxt
                            {--name=      : Nombre del sitio}
                            {--franchise= : Identificador único (ej: alquicarros)}
                            {--url=       : URL base del sitio Nuxt (ej: https://alquicarros.com)}
                            {--api-key=   : API key del endpoint /api/blog/wordpress-sync}';

    protected $description = 'Registra un nuevo sitio Nuxt en la base de datos';

    public function handle(): int
    {
        $name      = $this->option('name')      ?? $this->ask('Nombre del sitio');
        $franchise = $this->option('franchise') ?? $this->ask('Identificador único (ej: alquicarros)');
        $url       = $this->option('url')       ?? $this->ask('URL base del sitio Nuxt (ej: https://alquicarros.com)');
        $apiKey    = $this->option('api-key')   ?? $this->ask('API key del endpoint /api/blog/wordpress-sync');

        // Normalizar URL
        $url = rtrim($url, '/');

        // Verificar unicidad de franchise
        if (NuxtSite::where('franchise', $franchise)->exists()) {
            $this->error("Ya existe un sitio registrado con franchise: {$franchise}");
            return self::FAILURE;
        }

        // Validar endpoint
        $endpoint = "{$url}/api/blog/wordpress-sync";
        $this->info("Validando endpoint {$endpoint} ...");

        try {
            $response = Http::timeout(10)
                ->withHeaders(['x-api-key' => $apiKey])
                ->get($endpoint);

            if ($response->status() === 404) {
                $this->warn("El endpoint responde 404 — el sitio puede no estar disponible o la ruta no existe.");
                $this->warn("Se registrará el sitio de todas formas.");
            } else {
                // 200, 401, 422, etc. → endpoint existe
                $this->line('<fg=green>✓ Endpoint accesible (HTTP ' . $response->status() . ')</>');
            }
        } catch (\Exception $e) {
            $this->error("No se pudo conectar con {$url}: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Guardar sitio — el cast 'encrypted' del modelo encripta api_key automáticamente
        $site = NuxtSite::create([
            'site_name' => $name,
            'franchise' => $franchise,
            'site_url'  => $url,
            'api_key'   => $apiKey,
            'is_active' => true,
        ]);

        $this->newLine();
        $this->line('<fg=green>✓ Sitio Nuxt registrado exitosamente</>');
        $this->newLine();

        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID',        $site->id],
                ['Nombre',    $site->site_name],
                ['Franchise', $site->franchise],
                ['URL',       $site->site_url],
                ['Activo',    $site->is_active ? 'true' : 'false'],
            ]
        );

        return self::SUCCESS;
    }
}
