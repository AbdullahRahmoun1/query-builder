<?php

namespace Wever\AdvancedQueryBuilder\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\QueryBuilder;
use Wever\AdvancedQueryBuilder\Filtering\FilterHandler;
use Wever\AdvancedQueryBuilder\Support\ConfigManager;

class QueryBuilderFactory
{
    protected QueryBuilder $builder;

    protected ConfigManager $configManager;

    public function __construct(protected Model $model, Builder $query)
    {
        $this->configManager = new ConfigManager($model);

        // Pre-process the request to handle field dependencies
        $processedRequest = $this->preprocessRequest(request());

        // Explicitly pass the processed request to ensure filters are read correctly
        $this->builder = QueryBuilder::for($query, $processedRequest);
    }

    public function getExecutor(): QueryExecutor
    {
        $this->applyFiltering();
        $this->applySorting();
        $this->applyFieldSelection();

        // Pass the raw fields config directly to the Executor
        return new QueryExecutor($this->builder, $this->configManager->getFieldsConfig());
    }

    protected function applyFiltering(): void
    {
        $rawFilters = $this->configManager->getFilters();
        (new FilterHandler($rawFilters, $this->builder))->execute();
    }

    protected function applySorting(): void
    {
        $sorts = $this->configManager->getSorts();
        if (! empty($sorts)) {
            $this->builder->allowedSorts($sorts);
        }
    }

    protected function applyFieldSelection(): void
    {
        $fieldsConfig = $this->configManager->getFieldsConfig();

        // Get allowed fields (database columns only - accessors are handled in preprocessRequest)
        $allowedFields = $fieldsConfig['allowed'] ?? [];

        if (! empty($allowedFields)) {
            $this->builder->allowedFields($allowedFields);
        }
    }

    protected function preprocessRequest($request)
    {
        $fieldsConfig = $this->configManager->getFieldsConfig();
        $dependencies = $fieldsConfig['dependencies'] ?? [];
        $forcedFields = $fieldsConfig['forced'] ?? [];

        // Get the original fields parameter
        $originalFields = $request->get('fields');

        if (empty($originalFields)) {
            return $request;
        }

        // Convert to array if it's a string
        if (is_string($originalFields)) {
            $originalFields = explode(',', $originalFields);
        }

        // Process fields and resolve dependencies
        $processedFields = $forcedFields; // Always include forced fields

        foreach ($originalFields as $field) {
            $field = trim($field);
            if (isset($dependencies[$field])) {
                // This is an accessor field - include its dependencies instead
                $processedFields = array_merge($processedFields, $dependencies[$field]);
            } else {
                // This is a regular database field - include it
                $processedFields[] = $field;
            }
        }

        // Create a new request with the processed fields
        $newRequest = clone $request;
        $newRequest->merge(['fields' => implode(',', array_unique($processedFields))]);

        return $newRequest;
    }
}
