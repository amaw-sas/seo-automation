<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get backlinks with this link type
     */
    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }
}
