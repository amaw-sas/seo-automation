<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_strategy_id',
        'topic_research_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'meta_description',
        'primary_keyword_id',
        'secondary_keywords',
        'llm_provider',
        'llm_model',
        'llm_prompt_tokens',
        'llm_completion_tokens',
        'llm_cost_usd',
        'featured_image_url',
        'inline_images',
        'image_llm_provider',
        'image_generation_cost_usd',
        'status',
        'quality_score',
        'target_wordpress_site_id',
        'wordpress_post_id',
        'published_url',
        'published_at',
        'word_count',
        'reading_time_minutes',
        'seo_score',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'inline_images' => 'array',
        'llm_prompt_tokens' => 'integer',
        'llm_completion_tokens' => 'integer',
        'llm_cost_usd' => 'decimal:4',
        'image_generation_cost_usd' => 'decimal:4',
        'quality_score' => 'integer',
        'word_count' => 'integer',
        'reading_time_minutes' => 'integer',
        'seo_score' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Get the primary keyword
     */
    public function primaryKeyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'primary_keyword_id');
    }

    /**
     * Get the topic research
     */
    public function topicResearch(): BelongsTo
    {
        return $this->belongsTo(TopicResearch::class);
    }

    /**
     * Get the WordPress site
     */
    public function wordPressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class, 'target_wordpress_site_id');
    }

    /**
     * Get total cost (LLM + images)
     */
    public function getTotalCostAttribute(): float
    {
        return ($this->llm_cost_usd ?? 0) + ($this->image_generation_cost_usd ?? 0);
    }
}
