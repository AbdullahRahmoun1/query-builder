<?php

namespace Wever\AdvancedQueryBuilder\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Wever\AdvancedQueryBuilder\Filtering\Filter;

class TestCustomFilter implements Filter
{
    public function getOperationAlias(): string
    {
        return 'custom';
    }

    public function __invoke(Builder $query, mixed $value, mixed $property): void
    {
        // Simple custom filter that adds a prefix to the search
        $query->where($property, 'like', "custom_{$value}%");
    }
}
