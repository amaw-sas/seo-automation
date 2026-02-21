<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GapType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get keyword gaps with this type
     */
    public function keywordGaps(): HasMany
    {
        return $this->hasMany(KeywordGap::class);
    }
}
