<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WordPressSite extends Model
{
    use HasFactory;

    protected $table = 'wordpress_sites';

    protected $fillable = [
        'site_name',
        'site_url',
        'domain_id',
        'wp_rest_api_url',
        'wp_username',
        'wp_app_password',
        'default_category_id',
        'default_author_id',
        'auto_publish',
        'require_review',
        'is_active',
        'last_published_at',
        'total_posts_published',
    ];

    protected $casts = [
        'auto_publish' => 'boolean',
        'require_review' => 'boolean',
        'is_active' => 'boolean',
        'last_published_at' => 'datetime',
        'total_posts_published' => 'integer',
    ];

    /**
     * Get the domain
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the generated posts
     */
    public function generatedPosts(): HasMany
    {
        return $this->hasMany(GeneratedPost::class, 'target_wordpress_site_id');
    }
}
