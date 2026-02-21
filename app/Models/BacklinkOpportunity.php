<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkOpportunity extends Model
{
    protected $fillable = [
        'referring_domain_id',
        'competitor_domain_id',
        'our_domain_id',
        'opportunity_type',
        'priority',
        'status',
        'notes',
        'identified_at',
        'acquired_at',
    ];

    protected $casts = [
        'identified_at' => 'date',
        'acquired_at' => 'date',
    ];

    /**
     * Get the referring domain for this opportunity
     */
    public function referringDomain(): BelongsTo
    {
        return $this->belongsTo(ReferringDomain::class);
    }

    /**
     * Get the competitor domain that has this backlink
     */
    public function competitorDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'competitor_domain_id');
    }

    /**
     * Get our domain that wants this backlink
     */
    public function ourDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'our_domain_id');
    }
}
