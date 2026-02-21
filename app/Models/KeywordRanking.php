<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordRanking extends Model
{
    protected $fillable = [
        'keyword_id',
        'domain_id',
        'position',
        'url',
        'estimated_traffic',
        'snapshot_date',
        'snapshot_month',
    ];

    protected $casts = [
        'position' => 'integer',
        'estimated_traffic' => 'integer',
        'snapshot_date' => 'date',
    ];

    /**
     * Get keyword for this ranking
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /**
     * Get domain for this ranking
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Calculate snapshot_month from snapshot_date
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($ranking) {
            if ($ranking->snapshot_date && !$ranking->snapshot_month) {
                $ranking->snapshot_month = $ranking->snapshot_date->format('Y-m');
            }
        });
    }
}
