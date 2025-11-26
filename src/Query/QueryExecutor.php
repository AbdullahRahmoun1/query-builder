<?php

namespace Wever\AdvancedQueryBuilder\Query;

use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\Support\Schema;

class QueryExecutor
{
    protected bool $finalLogicHasBeenApplied = false;

    public function __construct(
        protected QueryBuilder $builder,
        protected array $fieldsConfig // Receives the raw config directly
    ) {}

    public function __call(string $method, array $parameters)
    {
        $this->applyFinalLogicIfNotApplied();
        return $this->builder->{$method}(...$parameters);
    }

    protected function applyFinalLogicIfNotApplied(): void
    {
        if ($this->finalLogicHasBeenApplied) {
            return;
        }
        $this->applyForcedAndDependentFields();
        $this->finalLogicHasBeenApplied = true;
    }

    protected function applyForcedAndDependentFields(): void
    {
        // Field selection and dependency resolution is now handled in QueryBuilderFactory
        // This method is kept for potential future field-related logic
        // Currently, it's a no-op since field processing happens during QueryBuilder setup
    }

    // --- Helper methods that used to be in the Handler ---

    protected function getAllowedFields(): array
    {
        if (isset($this->fieldsConfig['allowed']) && !is_null($this->fieldsConfig['allowed'])) {
            return $this->fieldsConfig['allowed'];
        }
        
        // Get database columns
        $allColumns = (new Schema)->getColumns($this->builder->getModel());
        $hidden = $this->getHiddenFields();
        $databaseFields = array_diff($allColumns, $hidden);
        
        // Add accessor fields that have dependencies defined
        $dependencies = $this->getFieldDependencies();
        $accessorFields = array_keys($dependencies);
        
        // Combine database fields and accessor fields
        return array_merge($databaseFields, $accessorFields);
    }

    protected function getHiddenFields(): array
    {
        return $this->fieldsConfig['hidden'] ?? [];
    }

    protected function getForcedFields(): array
    {
        return $this->fieldsConfig['forced'] ?? [];
    }

    protected function getFieldDependencies(): array
    {
        return $this->fieldsConfig['dependencies'] ?? [];
    }
}