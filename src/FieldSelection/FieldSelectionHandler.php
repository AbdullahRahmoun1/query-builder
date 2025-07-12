<?php

namespace Wever\AdvancedQueryBuilder\FieldSelection;

use Illuminate\Database\Eloquent\Model;
use Wever\AdvancedQueryBuilder\Support\Schema;

class FieldSelectionHandler
{
    public function __construct(
        protected Model $model,
        protected Schema $schema
    ) {}

    public function getAllowedFields(): array
    {
        if (property_exists($this->model, 'allowedFields')) {
            return $this->model->allowedFields;
        }

        $allColumns = $this->schema->getColumns($this->model);

        if (property_exists($this->model, 'hiddenFields')) {
            return array_diff($allColumns, $this->model->hiddenFields);
        }

        return $allColumns;
    }

    public function getForcedFields(): array
    {
        return property_exists($this->model, 'forcedFields') ? $this->model->forcedFields : [];
    }

    public function getFieldDependencies(): array
    {
        return property_exists($this->model, 'fieldDependencies') ? $this->model->fieldDependencies : [];
    }
}
