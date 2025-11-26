<?php

namespace Wever\AdvancedQueryBuilder\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wever\AdvancedQueryBuilder\Traits\HasAdvancedQueries;

class Category extends Model
{
    use HasFactory, HasAdvancedQueries;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected $filterable = [
        'name' => ['like', 'exact'],
        'slug' => 'exact',
    ];

    protected $sortable = [
        'name',
        'created_at',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
