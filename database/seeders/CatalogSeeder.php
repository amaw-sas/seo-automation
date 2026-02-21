<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed search_intents
        $intents = [
            ['name' => 'Informational', 'slug' => 'informational', 'description' => 'Usuario busca información'],
            ['name' => 'Commercial', 'slug' => 'commercial', 'description' => 'Usuario investiga opciones de compra'],
            ['name' => 'Transactional', 'slug' => 'transactional', 'description' => 'Usuario listo para comprar'],
            ['name' => 'Navigational', 'slug' => 'navigational', 'description' => 'Usuario busca sitio específico'],
            ['name' => 'Local', 'slug' => 'local', 'description' => 'Usuario busca servicio local'],
        ];
        DB::table('search_intents')->insert($intents);

        // Seed domain_types
        $domainTypes = [
            ['name' => 'Own', 'slug' => 'own', 'description' => 'Dominio propio'],
            ['name' => 'Competitor Local', 'slug' => 'competitor_local', 'description' => 'Competidor local directo'],
            ['name' => 'Competitor Regional', 'slug' => 'competitor_regional', 'description' => 'Competidor regional'],
            ['name' => 'Aggregator', 'slug' => 'aggregator', 'description' => 'Plataforma agregadora'],
            ['name' => 'Marketplace', 'slug' => 'marketplace', 'description' => 'Marketplace'],
            ['name' => 'Other', 'slug' => 'other', 'description' => 'Otro tipo'],
        ];
        DB::table('domain_types')->insert($domainTypes);

        // Seed link_types
        $linkTypes = [
            ['name' => 'Text', 'slug' => 'text', 'description' => 'Enlace de texto'],
            ['name' => 'Image', 'slug' => 'image', 'description' => 'Enlace en imagen'],
            ['name' => 'Nofollow', 'slug' => 'nofollow', 'description' => 'Enlace nofollow'],
            ['name' => 'Redirect', 'slug' => 'redirect', 'description' => 'Enlace con redirección'],
            ['name' => 'Frame', 'slug' => 'frame', 'description' => 'Enlace en iframe'],
        ];
        DB::table('link_types')->insert($linkTypes);

        // Seed gap_types
        $gapTypes = [
            ['name' => 'Missing', 'slug' => 'missing', 'description' => 'Keywords que no rankeamos'],
            ['name' => 'Weak', 'slug' => 'weak', 'description' => 'Keywords donde estamos débiles'],
            ['name' => 'Shared', 'slug' => 'shared', 'description' => 'Keywords compartidas'],
            ['name' => 'Untapped', 'slug' => 'untapped', 'description' => 'Keywords sin aprovechar'],
        ];
        DB::table('gap_types')->insert($gapTypes);

        // Seed categories
        $categories = [
            ['name' => 'Alquiler Carro', 'slug' => 'alquiler-carro', 'description' => 'Keywords generales de alquiler de carros'],
            ['name' => 'Alquiler Ciudad', 'slug' => 'alquiler-ciudad', 'description' => 'Keywords de alquiler por ciudad'],
            ['name' => 'Tipo Vehiculo', 'slug' => 'tipo-vehiculo', 'description' => 'Keywords por tipo de vehículo'],
            ['name' => 'Marca', 'slug' => 'marca', 'description' => 'Keywords con marcas específicas'],
            ['name' => 'Temporal', 'slug' => 'temporal', 'description' => 'Alquiler por día/semana/mes'],
            ['name' => 'Comparación', 'slug' => 'comparacion', 'description' => 'Keywords de comparación de precios'],
            ['name' => 'Long Tail', 'slug' => 'long-tail', 'description' => 'Keywords de cola larga'],
        ];
        DB::table('categories')->insert($categories);

        $this->command->info('Catálogos creados exitosamente.');
    }
}
