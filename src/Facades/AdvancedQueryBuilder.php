<?php

namespace Wever\AdvancedQueryBuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wever\AdvancedQueryBuilder\AdvancedQueryBuilder
 */
class AdvancedQueryBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wever\AdvancedQueryBuilder\AdvancedQueryBuilder::class;
    }
}
