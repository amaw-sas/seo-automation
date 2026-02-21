<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteAudit extends Model
{
    protected $fillable = [
        'domain_id',
        'pages_crawled',
        'site_health_score',
        'errors',
        'warnings',
        'notices',
        'audit_summary',
        'audit_date',
    ];

    protected $casts = [
        'pages_crawled' => 'integer',
        'site_health_score' => 'integer',
        'errors' => 'integer',
        'warnings' => 'integer',
        'notices' => 'integer',
        'audit_summary' => 'array',
        'audit_date' => 'date',
    ];

    /**
     * Get the domain that owns this audit
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the issues for this audit
     */
    public function issues(): HasMany
    {
        return $this->hasMany(SiteAuditIssue::class);
    }
}
