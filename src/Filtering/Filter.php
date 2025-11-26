<?php

namespace Wever\AdvancedQueryBuilder\Filtering;

use Illuminate\Database\Eloquent\Builder;

interface Filter extends \Spatie\QueryBuilder\Filters\Filter
{
    /**
     * Get the unique alias for this filter operation.
     * This will be used in the URL (e.g., 'c1' for '?filter[title_c1]=...').
     */
    public function getOperationAlias(): string;

    /**
     * Apply the filter logic to the query builder.
     */
    public function __invoke(Builder $query, mixed $value, mixed $property): void;
}
