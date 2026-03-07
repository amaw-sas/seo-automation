<?php

namespace Database\Factories;

use App\Models\NuxtSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class NuxtSiteFactory extends Factory
{
    protected $model = NuxtSite::class;

    public function definition(): array
    {
        return [
            'site_name' => $this->faker->company(),
            'franchise' => $this->faker->slug(2),
            'site_url'  => 'https://' . $this->faker->domainName(),
            'api_key'   => $this->faker->uuid(),
            'is_active' => true,
        ];
    }
}
