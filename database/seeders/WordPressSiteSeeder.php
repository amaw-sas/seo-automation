<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\WordPressSite;
use Illuminate\Database\Seeder;

class WordPressSiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscar el dominio principal
        $domain = Domain::where('domain', 'alquilatucarro.com.co')->first();

        // Crear sitio WordPress de ejemplo
        WordPressSite::create([
            'site_name' => 'Alquila Tu Carro',
            'site_url' => 'https://alquilatucarro.com.co',
            'domain_id' => $domain?->id,
            'wp_rest_api_url' => 'https://alquilatucarro.com.co',
            'wp_username' => env('WP_USERNAME', 'admin'),
            'wp_app_password' => encrypt(env('WP_APP_PASSWORD', 'CHANGE_ME')),
            'default_category_id' => 1, // Ajustar según categorías de WordPress
            'default_author_id' => 1, // Ajustar según usuarios de WordPress
            'auto_publish' => false, // Cambiar a true para auto-publicación
            'require_review' => true,
            'is_active' => true,
        ]);

        $this->command->info('✓ WordPress site seeded: alquilatucarro.com.co');
        $this->command->warn('⚠ Recuerda configurar WP_USERNAME y WP_APP_PASSWORD en .env');
    }
}
