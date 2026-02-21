<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backlink extends Model
{
    protected $fillable = [
        'referring_domain_id',
        'target_domain_id',
        'source_url',
        'target_url',
        'anchor_text',
        'link_type_id',
        'first_seen_at',
        'last_seen_at',
        'is_active',
        'is_spam',
        'quality_score',
    ];

    protected $casts = [
        'first_seen_at' => 'date',
        'last_seen_at' => 'date',
        'is_active' => 'boolean',
        'is_spam' => 'boolean',
        'quality_score' => 'integer',
    ];

    /**
     * Get referring domain
     */
    public function referringDomain(): BelongsTo
    {
        return $this->belongsTo(ReferringDomain::class);
    }

    /**
     * Get target domain
     */
    public function targetDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'target_domain_id');
    }

    /**
     * Get link type
     */
    public function linkType(): BelongsTo
    {
        return $this->belongsTo(LinkType::class);
    }

    /**
     * Scope: Only active backlinks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only non-spam backlinks
     */
    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    /**
     * Scope: Quality backlinks (not spam and score >= 3)
     */
    public function scopeQuality($query)
    {
        return $query->where('is_spam', false)
                    ->where(function($q) {
                        $q->whereNull('quality_score')
                          ->orWhere('quality_score', '>=', 3);
                    });
    }
}
