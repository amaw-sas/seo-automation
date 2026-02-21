<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchIntent extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get keywords with this intent
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class, 'intent_id');
    }
}
