<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'name',
        'department',
        'region',
        'population',
        'is_active',
    ];

    protected $casts = [
        'population' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get keywords for this city
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    /**
     * Get topic research for this city
     */
    public function topicResearch(): HasMany
    {
        return $this->hasMany(TopicResearch::class);
    }

    /**
     * Get content strategies for this city
     */
    public function contentStrategies(): HasMany
    {
        return $this->hasMany(ContentStrategy::class);
    }
}
