<?php

namespace Wever\AdvancedQueryBuilder\Filtering;

use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FilterHandler
{
    public function __construct(
        protected Model $model,
        protected QueryBuilder $builder
    ) {}

    public function execute(): void
    {
        if (! property_exists($this->model, 'filterable')) {
            return;
        }

        $allowedFilters = [];
        foreach ($this->model->filterable as $property => $config) {
            $generatedFilters = $this->createFiltersForProperty($property, $config);
            $allowedFilters = array_merge($allowedFilters, $generatedFilters);
        }

        if (! empty($allowedFilters)) {
            $this->builder->allowedFilters($allowedFilters);
        }
    }

    protected function createFiltersForProperty(int|string $property, string|array $config): array
    {
        // --- "Magic" Scope Mode ---
        // Handles: ['published'] where a 'scopePublished' method exists
        if (is_numeric($property) && is_string($config)) {
            $scopeMethodName = 'scope'.\Illuminate\Support\Str::studly($config);
            if (method_exists($this->model, $scopeMethodName)) {
                return [AllowedFilter::scope($config)];
            }
        }

        // --- Explicit Scope Modes ---
        // Handles: 'status' => 'scope' and 'status' => 'scope:withStatus'
        if (is_string($config) && str_starts_with($config, 'scope')) {
            $scopeName = str_contains($config, ':') ? substr($config, strpos($config, ':') + 1) : $property;

            return [AllowedFilter::scope($property, $scopeName)];
        }
        // --- Single-Operation Filters ---
        // Handles: ['status'] or ['type' => 'exact'] or ['title' => CustomFilter::class]
        if (is_string($config) || (is_numeric($property) && is_string($config))) {
            $dbColumn = is_numeric($property) ? $config : $property;
            $operationOrClass = is_numeric($property) ? 'exact' : $config;

            // If it's a custom class
            if (class_exists($operationOrClass) && is_subclass_of($operationOrClass, Filter::class)) {
                return [AllowedFilter::custom($dbColumn, new $operationOrClass, $dbColumn)];
            }

            // If it's a built-in alias
            $spatieMethod = config('query-builder.built_in_aliases')[$operationOrClass] ?? null;
            if ($spatieMethod) {
                return [AllowedFilter::$spatieMethod($dbColumn)];
            }
        }

        // --- Multi-Operation and Advanced Filters ---
        // Handles: ['title' => ['like', 'exact']] or ['author.name' => [...]]
        if (is_array($config)) {
            $dbColumn = $property;
            $alias = $config['alias'] ?? $dbColumn;
            $operations = $config['operations'] ?? $config;

            $filters = [];
            foreach ($operations as $key => $op) {
                $operationOrClass = is_string($key) ? $key : $op;
                $value = is_string($key) ? $op : $operationOrClass;

                // If it's a developer's custom filter class
                if (class_exists($value) && is_subclass_of($value, Filter::class)) {
                    $customFilter = new $value;
                    $opAlias = $customFilter->getOperationAlias();
                    $filterName = "{$alias}_{$opAlias}";
                    $filters[] = AllowedFilter::custom($filterName, $customFilter, $dbColumn);
                }
                // If it's a built-in Spatie filter alias
                elseif (is_string($value)) {
                    $spatieMethod = config('query-builder.built_in_aliases')[$value] ?? null;
                    if ($spatieMethod) {
                        $filterName = "{$alias}_{$value}";
                        $filters[] = AllowedFilter::$spatieMethod($filterName, $dbColumn);
                    }
                }
            }

            return $filters;
        }

        return [];
    }
}
