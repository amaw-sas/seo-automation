<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = [
        'keyword',
        'keyword_normalized',
        'city_id',
        'category_id',
        'intent_id',
        'search_volume_co',
        'search_volume_global',
        'keyword_difficulty',
        'cpc_usd',
        'serp_features',
        'notes',
    ];

    protected $casts = [
        'search_volume_co' => 'integer',
        'search_volume_global' => 'integer',
        'keyword_difficulty' => 'decimal:2',
        'cpc_usd' => 'decimal:2',
        'serp_features' => 'array',
    ];

    /**
     * Get city for this keyword
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get category for this keyword
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get intent for this keyword
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(SearchIntent::class, 'intent_id');
    }

    /**
     * Get rankings for this keyword
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(KeywordRanking::class);
    }

    /**
     * Get keyword gaps for this keyword
     */
    public function gaps(): HasMany
    {
        return $this->hasMany(KeywordGap::class);
    }

    /**
     * Normalize keyword for comparison
     */
    public static function normalize(string $keyword): string
    {
        return strtolower(trim($keyword));
    }
}
