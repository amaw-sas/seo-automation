<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get domains of this type
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
