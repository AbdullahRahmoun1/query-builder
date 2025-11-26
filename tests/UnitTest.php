<?php

use Wever\AdvancedQueryBuilder\Filtering\Filter;
use Wever\AdvancedQueryBuilder\Tests\Support\TestCustomFilter;
use Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider;

describe('Unit Tests (No Database Required)', function () {
    describe('Custom Filter Interface', function () {
        it('implements the Filter interface correctly', function () {
            $filter = new TestCustomFilter;

            expect($filter)->toBeInstanceOf(Filter::class);
            expect($filter->getOperationAlias())->toBe('custom');
        });
    });

    describe('Role Provider', function () {
        it('can set and get roles', function () {
            TestRoleProvider::clearRoles();
            TestRoleProvider::setRoles(['admin', 'user']);

            $provider = new TestRoleProvider;
            expect($provider->getUserRoles())->toBe(['admin', 'user']);
        });

        it('can clear roles', function () {
            TestRoleProvider::setRoles(['admin']);
            TestRoleProvider::clearRoles();

            $provider = new TestRoleProvider;
            expect($provider->getUserRoles())->toBe([]);
        });
    });

    describe('Package Structure', function () {
        it('has all required classes', function () {
            // Force autoload by trying to use the classes
            expect(file_exists(__DIR__.'/../src/AdvancedQueryBuilder.php'))->toBeTrue();
            expect(file_exists(__DIR__.'/../src/Traits/HasAdvancedQueries.php'))->toBeTrue();
            expect(file_exists(__DIR__.'/../src/Support/ConfigManager.php'))->toBeTrue();
            expect(file_exists(__DIR__.'/../src/Query/QueryExecutor.php'))->toBeTrue();
            expect(file_exists(__DIR__.'/../src/Query/QueryBuilderFactory.php'))->toBeTrue();

            // Test that classes can be loaded
            expect(class_exists(\Wever\AdvancedQueryBuilder\AdvancedQueryBuilder::class, true))->toBeTrue();
            expect(class_exists(\Wever\AdvancedQueryBuilder\Traits\HasAdvancedQueries::class, true))->toBeTrue();
            expect(class_exists(\Wever\AdvancedQueryBuilder\Support\ConfigManager::class, true))->toBeTrue();
            expect(class_exists(\Wever\AdvancedQueryBuilder\Query\QueryExecutor::class, true))->toBeTrue();
            expect(class_exists(\Wever\AdvancedQueryBuilder\Query\QueryBuilderFactory::class, true))->toBeTrue();
        });

        it('has all required interfaces', function () {
            expect(interface_exists(\Wever\AdvancedQueryBuilder\Filtering\Filter::class))->toBeTrue();
            expect(interface_exists(\Wever\AdvancedQueryBuilder\Auth\RoleProvider::class))->toBeTrue();
        });
    });
});
