<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordGap extends Model
{
    protected $fillable = [
        'keyword_id',
        'our_domain_id',
        'competitor_domain_id',
        'gap_type_id',
        'our_position',
        'competitor_position',
        'position_difference',
        'opportunity_score',
        'analysis_date',
    ];

    protected $casts = [
        'our_position' => 'integer',
        'competitor_position' => 'integer',
        'position_difference' => 'integer',
        'opportunity_score' => 'integer',
        'analysis_date' => 'date',
    ];

    /**
     * Get keyword for this gap
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /**
     * Get our domain
     */
    public function ourDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'our_domain_id');
    }

    /**
     * Get competitor domain
     */
    public function competitorDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'competitor_domain_id');
    }

    /**
     * Get gap type
     */
    public function gapType(): BelongsTo
    {
        return $this->belongsTo(GapType::class);
    }
}
