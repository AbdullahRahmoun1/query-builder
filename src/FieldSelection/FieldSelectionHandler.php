<?php

namespace Wever\AdvancedQueryBuilder\FieldSelection;

use Illuminate\Database\Eloquent\Model;
use Wever\AdvancedQueryBuilder\Support\Schema;

class FieldSelectionHandler
{
    public function __construct(
        protected Model $model,
        protected array $fieldsConfig,
        protected Schema $schema
    ) {}

    public function getAllowedFields(): array
    {
        if (isset($this->fieldsConfig['allowed']) && !is_null($this->fieldsConfig['allowed'])) {
            return $this->fieldsConfig['allowed'];
        }

        $allColumns = $this->schema->getColumns($this->model);
        $hidden = $this->fieldsConfig['hidden'] ?? [];

        return array_diff($allColumns, $hidden);
    }

    public function getForcedFields(): array
    {
        return $this->fieldsConfig['forced'] ?? [];
    }

    public function getFieldDependencies(): array
    {
        return $this->fieldsConfig['dependencies'] ?? [];
    }
}