<?php

namespace App\Models;

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
}
