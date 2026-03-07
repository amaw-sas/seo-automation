<?php

namespace Database\Factories;

use App\Models\WordPressSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordPressSiteFactory extends Factory
{
    protected $model = WordPressSite::class;

    public function definition(): array
    {
        return [
            'site_name'       => $this->faker->company(),
            'site_url'        => 'https://' . $this->faker->domainName(),
            'wp_rest_api_url' => 'https://' . $this->faker->domainName() . '/wp-json/wp/v2',
            'wp_username'     => $this->faker->userName(),
            'wp_app_password' => $this->faker->password(16),
            'is_active'       => true,
            'auto_publish'    => false,
            'require_review'  => false,
        ];
    }
}
