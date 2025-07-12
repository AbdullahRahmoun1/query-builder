<?php

namespace Wever\AdvancedQueryBuilder\Traits;

use Illuminate\Database\Eloquent\Builder;
use Wever\AdvancedQueryBuilder\Query\QueryExecutor;
use Wever\AdvancedQueryBuilder\Query\QueryBuilderFactory;

trait HasAdvancedQueries
{
    /**
     * @param Builder $query
     * @param array $extraConfig Deprecated, but kept for potential future use.
     */
    public function scopeBuildQuery(Builder $query, array $extraConfig = []): QueryExecutor
    {
        return (new QueryBuilderFactory($this, $query, $extraConfig))->getExecutor();
    }
}