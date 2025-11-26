<?php

namespace Wever\AdvancedQueryBuilder\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wever\AdvancedQueryBuilder\Traits\HasAdvancedQueries;

class User extends Model
{
    use HasAdvancedQueries, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $filterable = [
        'name' => 'like',
        'email' => 'exact',
        'role' => 'exact',
        'is_active' => 'exact',
    ];

    protected $sortable = [
        'name',
        'email',
        'created_at',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }
}
