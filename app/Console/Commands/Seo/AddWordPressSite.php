<?php

namespace App\Console\Commands\Seo;

use App\Models\WordPressSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AddWordPressSite extends Command
{
    protected $signature = 'seo:site:add:wordpress
                            {--name=         : Nombre del sitio}
                            {--url=          : URL principal (ej: https://mi-sitio.com)}
                            {--username=     : Usuario de WordPress}
                            {--app-password= : Application Password generado en WP Admin}
                            {--category-id=1 : ID de categoría por defecto}
                            {--author-id=1   : ID de autor por defecto}
                            {--no-review     : Desactivar require_review (default: true)}';

    protected $description = 'Registra un nuevo sitio WordPress en la base de datos';

    public function handle(): int
    {
        $name        = $this->option('name')         ?? $this->ask('Nombre del sitio');
        $url         = $this->option('url')          ?? $this->ask('URL del sitio (ej: https://mi-sitio.com)');
        $username    = $this->option('username')     ?? $this->ask('Usuario de WordPress');
        $appPassword = $this->option('app-password') ?? $this->ask('Application Password (generado en WP Admin)');

        // Normalizar URL (quitar slash final)
        $url = rtrim($url, '/');

        // Verificar unicidad de site_url
        if (WordPressSite::where('site_url', $url)->exists()) {
            $this->error("Ya existe un sitio registrado con la URL: {$url}");
            return self::FAILURE;
        }

        // Validar credenciales contra la API de WordPress
        $this->info("Validando credenciales contra {$url}/wp-json/wp/v2/users/me ...");

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($username, $appPassword)
                ->get("{$url}/wp-json/wp/v2/users/me");

            if (! $response->successful()) {
                $this->error("Credenciales inválidas (HTTP {$response->status()}). Verifica usuario y Application Password.");
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("No se pudo conectar con {$url}: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->line('<fg=green>✓ Credenciales válidas</>');

        // Guardar sitio
        $site = WordPressSite::create([
            'site_name'           => $name,
            'site_url'            => $url,
            'wp_rest_api_url'     => $url,
            'wp_username'         => $username,
            'wp_app_password'     => encrypt($appPassword),
            'default_category_id' => (int) $this->option('category-id'),
            'default_author_id'   => (int) $this->option('author-id'),
            'auto_publish'        => false,
            'require_review'      => ! $this->option('no-review'),
            'is_active'           => true,
        ]);

        $this->newLine();
        $this->line('<fg=green>✓ Sitio WordPress registrado exitosamente</>');
        $this->newLine();

        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID',              $site->id],
                ['Nombre',          $site->site_name],
                ['URL',             $site->site_url],
                ['Usuario',         $site->wp_username],
                ['Categoría',       $site->default_category_id],
                ['Autor',           $site->default_author_id],
                ['Require review',  $site->require_review ? 'true' : 'false'],
                ['Auto publish',    $site->auto_publish   ? 'true' : 'false'],
                ['Activo',          $site->is_active      ? 'true' : 'false'],
            ]
        );

        return self::SUCCESS;
    }
}
