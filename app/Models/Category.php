<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get keywords in this category
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }
}
