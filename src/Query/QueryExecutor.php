<?php

namespace Wever\AdvancedQueryBuilder\Query;

use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\FieldSelection\FieldSelectionHandler;

class QueryExecutor
{
    /**
     * A flag to ensure our final logic only runs once per query execution.
     */
    protected bool $finalLogicHasBeenApplied = false;

    public function __construct(
        protected QueryBuilder $builder,
        protected FieldSelectionHandler $fieldSelectionHandler
    ) {}

    /**
     * This magic method intercepts any method call that doesn't exist on this class
     * (e.g., get, paginate, toSql, dd, etc.), applies our final logic,
     * and then forwards the call to the underlying Spatie builder.
     */
    public function __call(string $method, array $parameters)
    {
        $this->applyFinalLogicIfNotApplied();

        return $this->builder->{$method}(...$parameters);
    }

    /**
     * A simple guard to prevent the enhancement logic from running multiple times.
     */
    protected function applyFinalLogicIfNotApplied(): void
    {
        if ($this->finalLogicHasBeenApplied) {
            return;
        }

        $this->applyFinalLogic();

        $this->finalLogicHasBeenApplied = true;
    }

    /**
     * The main orchestrator for all final query enhancements.
     */
    protected function applyFinalLogic(): void
    {
        // This is where all future enhancements will be called from.
        $this->applyForcedAndDependentFields();
    }

    /**
     * The complete logic for handling field dependencies and forced fields.
     */
    /**
     * The complete logic for handling field dependencies and forced fields.
     */
    protected function applyForcedAndDependentFields(): void
    {
        $eloquentBuilder = $this->builder->getEloquentBuilder();
        $modelTable = $this->builder->getModel()->getTable();
        $requestedFields = $this->builder->getRequestedFieldsForRelatedTable($modelTable);

        if (empty($requestedFields)) {
            return;
        }

        // --- THE FIX: Start with forced fields, then add requested fields ---

        // 1. Start with the fields that must always be present.
        $finalFields = $this->fieldSelectionHandler->getForcedFields();

        // 2. Add the fields the user explicitly asked for.
        $finalFields = array_merge($finalFields, $requestedFields);

        // 3. Add any dependencies triggered by the requested fields.
        $dependencies = $this->fieldSelectionHandler->getFieldDependencies();
        foreach ($requestedFields as $field) {
            if (isset($dependencies[$field])) {
                $finalFields = array_merge($finalFields, $dependencies[$field]);
            }
        }

        // Apply the final, unique list of columns to the query's SELECT clause.
        $eloquentBuilder->select(array_unique($finalFields));
    }
}
