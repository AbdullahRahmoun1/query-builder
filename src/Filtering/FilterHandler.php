<?php

namespace Wever\AdvancedQueryBuilder\Filtering;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FilterHandler
{
    public function __construct(
        protected array $filterableConfig,
        protected QueryBuilder $builder
    ) {}

    public function execute(): void
    {
        if (empty($this->filterableConfig)) {
            return;
        }

        $allowedFilters = [];
        foreach ($this->filterableConfig as $property => $config) {
            $generatedFilters = $this->createFiltersForProperty($property, $config);
            $allowedFilters = array_merge($allowedFilters, $generatedFilters);
        }

        if (!empty($allowedFilters)) {
            $this->builder->allowedFilters($allowedFilters);
        }
    }

    protected function createFiltersForProperty(int|string $property, string|array $config): array
    {
        // This method now expects "magic" scopes to have been pre-processed by the ConfigManager.
        // It handles the remaining single-operation and multi-operation modes.

        if (is_string($config)) {
            $dbColumn = is_numeric($property) ? $config : $property;
            $operationOrClass = is_numeric($property) ? 'exact' : $config;

            if (class_exists($operationOrClass) && is_subclass_of($operationOrClass, Filter::class)) {
                return [AllowedFilter::custom($dbColumn, new $operationOrClass(), $dbColumn)];
            }

            if (str_starts_with($operationOrClass, 'scope:')) {
                $scopeName = \Illuminate\Support\Str::after($operationOrClass, ':');
                return [AllowedFilter::scope($dbColumn, $scopeName)];
            }

            try {
                $builtInAliases = config('query-builder.built_in_aliases');
                $spatieMethod = $builtInAliases[$operationOrClass] ?? null;
            } catch (\Exception $e) {
                // Fallback aliases when config is not available
                $fallbackAliases = [
                    'exact' => 'exact',
                    'like' => 'partial',
                    'scope' => 'scope',
                    'beginsWithStrict' => 'beginsWithStrict'
                ];
                $spatieMethod = $fallbackAliases[$operationOrClass] ?? null;
            }
            if ($spatieMethod) {
                return [AllowedFilter::$spatieMethod($dbColumn)];
            }
        }

        if (is_array($config)) {
            $dbColumn = $property;
            $alias = $config['alias'] ?? $dbColumn;
            $operations = $config['operations'] ?? $config;

            $filters = [];
            foreach ($operations as $key => $op) {
                $opValue = is_string($key) ? $op : null;
                $opName = is_string($key) ? $key : $op;

                if (class_exists($op) && is_subclass_of($op, Filter::class)) {
                    $customFilter = new $op();
                    $opAlias = $customFilter->getOperationAlias();
                    $filterName = "{$alias}_{$opAlias}";
                    $filters[] = AllowedFilter::custom($filterName, $customFilter, $dbColumn);
                }
                elseif (is_string($op)) {
                    try {
                        $builtInAliases = config('query-builder.built_in_aliases');
                        $spatieMethod = $builtInAliases[$op] ?? null;
                    } catch (\Exception $e) {
                        // Fallback aliases when config is not available
                        $fallbackAliases = [
                            'exact' => 'exact',
                            'like' => 'partial',
                            'scope' => 'scope',
                            'beginsWithStrict' => 'beginsWithStrict'
                        ];
                        $spatieMethod = $fallbackAliases[$op] ?? null;
                    }
                    if ($spatieMethod) {
                        $filterName = "{$alias}_{$op}";
                        $filters[] = AllowedFilter::$spatieMethod($filterName, $dbColumn);
                    }
                }
            }
            return $filters;
        }

        return [];
    }
}