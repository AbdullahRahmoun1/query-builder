<?php

namespace Wever\AdvancedQueryBuilder\Filtering\Strategies;

use Spatie\QueryBuilder\AllowedFilter;
use Wever\AdvancedQueryBuilder\Filtering\Filter;

class ExactFilter implements Filter
{
    public function __invoke(string $property): AllowedFilter
    {
        return AllowedFilter::exact($property);
    }
}