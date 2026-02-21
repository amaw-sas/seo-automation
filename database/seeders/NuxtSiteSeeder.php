<?php

namespace Database\Seeders;

use App\Models\NuxtSite;
use Illuminate\Database\Seeder;

class NuxtSiteSeeder extends Seeder
{
    public function run(): void
    {
        $apiKey = env('NUXT_BLOG_API_KEY', '12cf007b-0000-0000-0000-000000000000');

        NuxtSite::insert([
            [
                'id'          => 1,
                'site_name'   => 'Alquilatucarro',
                'franchise'   => 'alquilatucarro',
                'site_url'    => 'https://alquilatucarro.com',
                'api_key'     => encrypt($apiKey),
                'domain_id'   => 1,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'id'          => 2,
                'site_name'   => 'Alquicarros',
                'franchise'   => 'alquicarros',
                'site_url'    => 'http://localhost:3000',
                'api_key'     => encrypt($apiKey),
                'domain_id'   => 3,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        $this->command->info('✓ NuxtSite seeded: alquilatucarro (id=1), alquicarros (id=2)');
        $this->command->warn('⚠ Recuerda configurar NUXT_BLOG_API_KEY en .env');
    }
}
