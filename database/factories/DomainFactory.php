<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\DomainType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        $domainType = DomainType::firstOrCreate(
            ['slug' => 'own'],
            ['name' => 'Own', 'description' => 'Our own domain']
        );

        return [
            'domain'         => $this->faker->domainName(),
            'domain_type_id' => $domainType->id,
            'is_own'         => true,
            'is_active'      => true,
        ];
    }
}
