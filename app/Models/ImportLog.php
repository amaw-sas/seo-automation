<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'domain_id',
        'import_type',
        'source_file',
        'snapshot_date',
        'keywords_added',
        'keywords_updated',
        'rankings_added',
        'rankings_updated',
        'backlinks_added',
        'backlinks_deactivated',
        'duration_seconds',
        'status',
        'error_message',
        'changelog',
        'completed_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'changelog' => 'array',
        'completed_at' => 'datetime',
        'keywords_added' => 'integer',
        'keywords_updated' => 'integer',
        'rankings_added' => 'integer',
        'rankings_updated' => 'integer',
        'backlinks_added' => 'integer',
        'backlinks_deactivated' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /**
     * Get the domain for this import log
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Mark import as completed
     */
    public function markAsCompleted(array $changelog = []): void
    {
        $this->update([
            'status' => 'completed',
            'changelog' => $changelog,
            'completed_at' => now(),
            'duration_seconds' => $this->created_at->diffInSeconds(now()),
        ]);
    }

    /**
     * Mark import as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'duration_seconds' => $this->created_at->diffInSeconds(now()),
        ]);
    }
}
