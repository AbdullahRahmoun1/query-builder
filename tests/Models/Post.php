<?php

namespace Wever\AdvancedQueryBuilder\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wever\AdvancedQueryBuilder\Traits\HasAdvancedQueries;

class Post extends Model
{
    use HasFactory, HasAdvancedQueries;

    protected $fillable = [
        'title',
        'content',
        'status',
        'view_count',
        'author_id',
        'category_id',
        'published_at',
        'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
    ];

    // Basic filtering configuration
    protected $filterable = [
        'status' => 'exact',
        'title' => ['like', 'exact'],
        'view_count' => ['exact', 'like'],
        'is_featured' => 'exact',
        'published' => 'scope', // Will use scopePublished
        'author.name' => ['alias' => 'author', 'operations' => ['like']],
    ];

    protected $sortable = [
        'title',
        'created_at',
        'view_count',
        'published_at',
    ];

    protected $allowedFields = [
        'id',
        'title',
        'content',
        'status',
        'view_count',
        'author_id',
        'category_id',
        'published_at',
        'is_featured',
        'created_at',
        'updated_at',
    ];

    protected $forcedFields = ['id'];

    protected $fieldDependencies = [
        'full_title' => ['title', 'status'], // Accessor dependency
    ];

    // Relationships
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Scopes for testing
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Accessor for testing field dependencies
    public function getFullTitleAttribute()
    {
        return $this->title . ' (' . $this->status . ')';
    }
}
