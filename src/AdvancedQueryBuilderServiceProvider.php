<?php

namespace Wever\AdvancedQueryBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wever\AdvancedQueryBuilder\Commands\AdvancedQueryBuilderCommand;

class AdvancedQueryBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('query-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_query_builder_table')
            ->hasCommand(AdvancedQueryBuilderCommand::class);
    }
}
