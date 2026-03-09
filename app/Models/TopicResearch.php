<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicResearch extends Model
{
    protected $table = 'topic_research';

    protected $fillable = [
        'title',
        'city_id',
        'potential_traffic',
        'competition_level',
        'recommended_keywords',
        'content_outline',
    ];

    protected $casts = [
        'potential_traffic' => 'integer',
        'competition_level' => 'integer',
        'recommended_keywords' => 'array',
    ];

    /**
     * Get the city for this topic research
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Scope: topics not yet generated for the given WordPress site.
     * Ordered by priority score: potential_traffic / (competition_level + 1)
     */
    public function scopeAvailableForSite(Builder $query, int $siteId): Builder
    {
        return $query
            ->whereNotIn('id', function ($sub) use ($siteId) {
                $sub->select('topic_research_id')
                    ->from('generated_posts')
                    ->whereNotNull('topic_research_id')
                    ->where('target_wordpress_site_id', $siteId);
            })
            ->orderByRaw('potential_traffic / (competition_level + 1) DESC');
    }
}
