<?php

namespace Wever\AdvancedQueryBuilder\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\Filtering\FilterHandler;

class QueryBuilderFactory
{
    protected QueryBuilder $builder;
    protected Model $model;
    protected array $extraFilters;

    // Internal arrays to collect configuration
    protected array $allowedFilters = [];
    protected array $allowedSorts = []; // This will now be used
    protected array $allowedFields = [];

    public function __construct(Model $model, Builder $query, array $extraFilters = [])
    {
        $this->model = $model;
        $this->extraFilters = $extraFilters;
        $this->builder = QueryBuilder::for($query);
    }

    public function applyFiltering(): self
    {
        if (!property_exists($this->model, 'filterable')) {
            return $this;
        }
        (new FilterHandler($this->model, $this->builder))->execute();
        return $this;
    }

    public function applySorting(): self
    {
        // Check if the property exists on the model
        if (property_exists($this->model, 'sortable')) {
            // Add the model's sortable config to our internal array
            $this->allowedSorts = array_merge($this->allowedSorts, $this->model->sortable ?? []);
        }
        return $this;
    }

    public function applyFieldSelection(): self
    {
        // We will build this logic in a future step.
        return $this;
    }

    public function applySearching(): self
    {
        // We will build this logic in a future step.
        return $this;
    }

    public function getBuilder(): QueryBuilder
    {
        if (! empty($this->allowedSorts)) {
            $this->builder->allowedSorts($this->allowedSorts);
        }
        // NEW: Apply the collected filters in ONE call
        if (! empty($this->allowedFilters)) {
            $this->builder->allowedFilters($this->allowedFilters);
        }
        return $this->builder;
    }
}
