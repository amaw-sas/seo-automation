<?php

namespace Database\Factories;

use App\Models\GeneratedPost;
use App\Models\Keyword;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeneratedPostFactory extends Factory
{
    protected $model = GeneratedPost::class;

    public function definition(): array
    {
        $title   = $this->faker->sentence(6);
        $kw      = $this->faker->words(3, true);
        $keyword = Keyword::firstOrCreate(
            ['keyword_normalized' => strtolower(trim($kw))],
            ['keyword'            => $kw],
        );

        return [
            'title'                      => $title,
            'slug'                       => Str::slug($title),
            'content'                    => '<p>' . $this->faker->paragraph(5) . '</p>',
            'excerpt'                    => $this->faker->sentence(20),
            'meta_description'           => $this->faker->sentence(15),
            'primary_keyword_id'         => $keyword->id,
            'status'                     => 'draft',
            'quality_score'              => 80,
            'word_count'                 => 500,
            'llm_provider'               => 'anthropic',
            'llm_model'                  => 'claude-3-5-sonnet',
            'llm_cost_usd'               => 0.01,
            'image_generation_cost_usd'  => 0.0,
        ];
    }
}
