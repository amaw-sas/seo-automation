<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferringDomain extends Model
{
    protected $fillable = [
        'domain',
        'authority_score',
        'category',
        'total_backlinks',
        'is_spam',
    ];

    protected $casts = [
        'authority_score' => 'integer',
        'total_backlinks' => 'integer',
        'is_spam' => 'boolean',
    ];

    /**
     * Get backlinks from this domain
     */
    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }

    /**
     * Get backlink opportunities from this domain
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(BacklinkOpportunity::class);
    }

    /**
     * Scope: Only non-spam referring domains
     */
    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    /**
     * Scope: High authority domains (AS >= 40)
     */
    public function scopeHighAuthority($query)
    {
        return $query->where('authority_score', '>=', 40);
    }
}
