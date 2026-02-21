<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 19 ciudades colombianas principales para alquiler de carros
        $cities = [
            ['name' => 'Bogotá', 'department' => 'Cundinamarca', 'region' => 'Andina', 'population' => 7743955],
            ['name' => 'Medellín', 'department' => 'Antioquia', 'region' => 'Andina', 'population' => 2508452],
            ['name' => 'Cali', 'department' => 'Valle del Cauca', 'region' => 'Pacífica', 'population' => 2227642],
            ['name' => 'Barranquilla', 'department' => 'Atlántico', 'region' => 'Caribe', 'population' => 1232766],
            ['name' => 'Cartagena', 'department' => 'Bolívar', 'region' => 'Caribe', 'population' => 1028736],
            ['name' => 'Cúcuta', 'department' => 'Norte de Santander', 'region' => 'Andina', 'population' => 711715],
            ['name' => 'Bucaramanga', 'department' => 'Santander', 'region' => 'Andina', 'population' => 613400],
            ['name' => 'Pereira', 'department' => 'Risaralda', 'region' => 'Andina', 'population' => 488839],
            ['name' => 'Santa Marta', 'department' => 'Magdalena', 'region' => 'Caribe', 'population' => 499192],
            ['name' => 'Ibagué', 'department' => 'Tolima', 'region' => 'Andina', 'population' => 529635],
            ['name' => 'Villavicencio', 'department' => 'Meta', 'region' => 'Orinoquía', 'population' => 531275],
            ['name' => 'Manizales', 'department' => 'Caldas', 'region' => 'Andina', 'population' => 434403],
            ['name' => 'Neiva', 'department' => 'Huila', 'region' => 'Andina', 'population' => 357392],
            ['name' => 'Armenia', 'department' => 'Quindío', 'region' => 'Andina', 'population' => 315328],
            ['name' => 'Pasto', 'department' => 'Nariño', 'region' => 'Andina', 'population' => 392930],
            ['name' => 'Montería', 'department' => 'Córdoba', 'region' => 'Caribe', 'population' => 504482],
            ['name' => 'Popayán', 'department' => 'Cauca', 'region' => 'Andina', 'population' => 277270],
            ['name' => 'Valledupar', 'department' => 'Cesar', 'region' => 'Caribe', 'population' => 493943],
            ['name' => 'Riohacha', 'department' => 'La Guajira', 'region' => 'Caribe', 'population' => 279867],
        ];

        DB::table('cities')->insert($cities);

        $this->command->info('19 ciudades colombianas insertadas exitosamente.');
    }
}
