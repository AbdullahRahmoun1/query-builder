<?php

namespace Wever\AdvancedQueryBuilder\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Wever\AdvancedQueryBuilder\AdvancedQueryBuilderServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Wever\\AdvancedQueryBuilder\\Tests\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            AdvancedQueryBuilderServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Try SQLite first, fallback to array driver if SQLite is not available
        try {
            new \PDO('sqlite::memory:');
            config()->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        } catch (\Exception $e) {
            // Fallback to array driver for environments without SQLite
            config()->set('database.connections.testing', [
                'driver' => 'array',
            ]);
        }

        // Set up the query builder config for testing
        config()->set('query-builder.role_provider', \Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider::class);
        config()->set('query-builder.built_in_aliases', [
            'exact' => 'exact',
            'like' => 'partial',
            'scope' => 'scope',
        ]);
    }

    protected function setUpDatabase(): void
    {
        // Only run migrations if we have a proper database connection
        try {
            // Test if we can create tables
            \Schema::create('test_connection', function ($table) {
                $table->id();
            });
            \Schema::dropIfExists('test_connection');

            // Run test migrations
            foreach (File::allFiles(__DIR__.'/database/migrations') as $migration) {
                (include $migration->getRealPath())->up();
            }
        } catch (\Exception $e) {
            // Skip database setup if driver is not available
            // This allows unit tests to run without database
        }
    }
}
