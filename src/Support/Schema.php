<?php

namespace Wever\AdvancedQueryBuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class Schema
{
    protected static array $columnCache = [];

    public function getColumns(Model $model): array
    {
        $tableName = $model->getTable();

        if (isset(static::$columnCache[$tableName])) {
            return static::$columnCache[$tableName];
        }

        return static::$columnCache[$tableName] = SchemaFacade::getColumnListing($tableName);
    }
}