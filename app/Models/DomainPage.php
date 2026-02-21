<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainPage extends Model
{
    protected $fillable = [
        'domain_id',
        'url',
        'traffic',
        'keywords_count',
        'backlinks_count',
        'snapshot_date',
    ];

    protected $casts = [
        'traffic' => 'integer',
        'keywords_count' => 'integer',
        'backlinks_count' => 'integer',
        'snapshot_date' => 'date',
    ];

    /**
     * Get the domain that owns this page
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
