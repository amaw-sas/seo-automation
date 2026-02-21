<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditIssue extends Model
{
    protected $fillable = [
        'site_audit_id',
        'issue_type',
        'severity',
        'description',
        'affected_pages',
        'example_url',
    ];

    protected $casts = [
        'affected_pages' => 'integer',
    ];

    /**
     * Get the site audit that owns this issue
     */
    public function siteAudit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class);
    }
}
