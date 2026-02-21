<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NuxtSite extends Model
{
    protected $table = 'nuxt_sites';

    protected $fillable = [
        'site_name',
        'franchise',
        'site_url',
        'api_key',
        'domain_id',
        'is_active',
        'last_synced_at',
        'total_posts_synced',
    ];

    protected $casts = [
        'api_key'            => 'encrypted',
        'is_active'          => 'boolean',
        'last_synced_at'     => 'datetime',
        'total_posts_synced' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
