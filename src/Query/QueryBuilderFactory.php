<?php

namespace Wever\AdvancedQueryBuilder\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\FieldSelection\FieldSelectionHandler;
use Wever\AdvancedQueryBuilder\Filtering\FilterHandler;
use Wever\AdvancedQueryBuilder\Support\Schema;

class QueryBuilderFactory
{
    protected QueryBuilder $builder;

    protected Request $request;

    public function __construct(protected Model $model, Builder $query, protected array $extraConfig = [])
    {
        $this->request = request();
        $this->builder = QueryBuilder::for($query, $this->request);
    }

    public function getExecutor(): QueryExecutor
    {
        $this->applyFiltering();
        $this->applySorting();
        $this->applyFieldSelection();

        $fieldSelectionHandler = new FieldSelectionHandler($this->model, new Schema);

        return new QueryExecutor($this->builder, $fieldSelectionHandler);
    }

    protected function applyFiltering(): void
    {
        (new FilterHandler($this->model, $this->builder))->execute();
    }

    protected function applySorting(): void
    {
        if (property_exists($this->model, 'sortable')) {
            $this->builder->allowedSorts($this->model->sortable);
        }
    }

    protected function applyFieldSelection(): void
    {
        $handler = new FieldSelectionHandler($this->model, new Schema);
        $this->builder->allowedFields($handler->getAllowedFields());
    }
}
