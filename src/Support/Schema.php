<?php

namespace Wever\AdvancedQueryBuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class Schema
{
    /**
     * A simple cache to avoid repeatedly hitting the database for schema info.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $columnCache = [];

    /**
     * Get all column names for a given model's table.
     */
    public function getColumns(Model $model): array
    {
        $tableName = $model->getTable();

        if (isset(static::$columnCache[$tableName])) {
            return static::$columnCache[$tableName];
        }

        return static::$columnCache[$tableName] = SchemaFacade::getColumnListing($tableName);
    }
}
