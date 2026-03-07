<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'domain_type_id',
        'is_own',
        'authority_score',
        'total_backlinks',
        'referring_domains',
        'organic_traffic',
        'organic_keywords',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_own' => 'boolean',
        'is_active' => 'boolean',
        'authority_score' => 'integer',
        'total_backlinks' => 'integer',
        'referring_domains' => 'integer',
        'organic_traffic' => 'integer',
        'organic_keywords' => 'integer',
    ];

    /**
     * Get domain type
     */
    public function domainType(): BelongsTo
    {
        return $this->belongsTo(DomainType::class);
    }

    /**
     * Get keyword rankings for this domain
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(KeywordRanking::class);
    }

    /**
     * Get backlinks to this domain
     */
    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class, 'target_domain_id');
    }

    /**
     * Get pages for this domain
     */
    public function pages(): HasMany
    {
        return $this->hasMany(DomainPage::class);
    }

    /**
     * Get site audits for this domain
     */
    public function siteAudits(): HasMany
    {
        return $this->hasMany(SiteAudit::class);
    }
}
