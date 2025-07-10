<?php

namespace Wever\AdvancedQueryBuilder\Filtering;

use Spatie\QueryBuilder\AllowedFilter;

interface Filter
{
    public function __invoke(string $property): AllowedFilter;
}