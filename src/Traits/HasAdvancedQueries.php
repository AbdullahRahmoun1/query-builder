<?php

namespace Wever\AdvancedQueryBuilder\Traits;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\Support\QueryBuilderFactory;

/**
 * @property array $filterable
 * @property array $sortable
 * @property array $allowedFields
 * @property array $searchable
 */
trait HasAdvancedQueries
{
    // ... (keep existing code)

    public function scopeBuildQuery(Builder $query, array $extraFilters = []): QueryBuilder
    {
        $factory = new QueryBuilderFactory($this, $query, $extraFilters);

        $factory->applySorting();

        // NEW: Tell the factory to apply filtering
        $factory->applyFiltering();

        return $factory->getBuilder();
    }
}
