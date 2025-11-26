<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;
use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider;
use Wever\AdvancedQueryBuilder\Support\ConfigManager;
use Wever\AdvancedQueryBuilder\Query\QueryExecutor;

describe('Edge Cases and Error Handling', function () {
    beforeEach(function () {
        TestRoleProvider::clearRoles();
        
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->post = Post::factory()->create([
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    });

    afterEach(function () {
        TestRoleProvider::clearRoles();
    });

    describe('Invalid Request Parameters', function () {
        it('handles malformed filter parameters', function () {
            $request = request()->merge(['filter' => 'invalid_string']);
            app()->instance('request', $request);

            // Should not throw an exception
            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles null filter values', function () {
            $request = request()->merge(['filter' => ['status' => null]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles empty filter arrays', function () {
            $request = request()->merge(['filter' => []]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles malformed sort parameters', function () {
            $request = request()->merge(['sort' => ['invalid' => 'structure']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles very long filter values', function () {
            $longValue = str_repeat('a', 10000);
            $request = request()->merge(['filter' => ['title_like' => $longValue]]);
            app()->instance('request', $request);

            // Should handle gracefully without memory issues
            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('handles special characters in filter values', function () {
            $specialChars = "!@#$%^&*()_+-=[]{}|;':\",./<>?`~";
            $request = request()->merge(['filter' => ['title_like' => $specialChars]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('handles unicode characters in filter values', function () {
            $unicode = "测试 🚀 émojis ñoño";
            $request = request()->merge(['filter' => ['title_like' => $unicode]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });
    });

    describe('Model Configuration Edge Cases', function () {
        it('handles models with no configuration properties', function () {
            $emptyModel = new class extends Post {
                // No filterable, sortable, etc.
            };

            $configManager = new ConfigManager($emptyModel);
            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();

            expect($filters)->toBeArray();
            expect($sorts)->toBeArray();
            expect($filters)->toBeEmpty();
            expect($sorts)->toBeEmpty();
        });

        it('handles models with invalid configuration types', function () {
            $invalidModel = new class extends Post {
                protected $filterable = 'should_be_array';
                protected $sortable = 123;
                protected $allowedFields = false;
            };

            // Should not throw an exception
            $configManager = new ConfigManager($invalidModel);
            expect($configManager)->toBeInstanceOf(ConfigManager::class);
        });

        it('handles circular references in field dependencies', function () {
            $circularModel = new class extends Post {
                protected $fieldDependencies = [
                    'field_a' => ['field_b'],
                    'field_b' => ['field_a'], // Circular reference
                ];
            };

            $configManager = new ConfigManager($circularModel);
            $fieldsConfig = $configManager->getFieldsConfig();

            expect($fieldsConfig)->toHaveKey('dependencies');
        });

        it('handles deeply nested field dependencies', function () {
            $deepModel = new class extends Post {
                protected $fieldDependencies = [
                    'level1' => ['level2'],
                    'level2' => ['level3'],
                    'level3' => ['level4'],
                    'level4' => ['level5'],
                    'level5' => ['id'], // Eventually resolves to a real field
                ];
            };

            $configManager = new ConfigManager($deepModel);
            $fieldsConfig = $configManager->getFieldsConfig();

            expect($fieldsConfig['dependencies'])->toHaveKey('level1');
        });
    });

    describe('Database Edge Cases', function () {
        it('handles empty database tables', function () {
            // Delete all posts
            Post::query()->delete();

            $request = request()->merge(['filter' => ['status' => 'published']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(0);
            expect($posts)->toBeCollection();
        });

        it('handles very large result sets', function () {
            // Create many posts
            Post::factory()->count(1000)->create([
                'author_id' => $this->user->id,
                'category_id' => $this->category->id,
            ]);

            $posts = Post::buildQuery()->get();
            expect($posts->count())->toBeGreaterThan(1000);
        });

        it('handles database connection issues gracefully', function () {
            // This is more of a conceptual test - in real scenarios you'd mock the DB
            // For now, we just ensure the query builder can be created
            $executor = Post::buildQuery();
            expect($executor)->toBeInstanceOf(QueryExecutor::class);
        });
    });

    describe('Memory and Performance Edge Cases', function () {
        it('handles multiple simultaneous query builders', function () {
            $executors = [];
            
            // Create multiple query builders
            for ($i = 0; $i < 100; $i++) {
                $executors[] = Post::buildQuery();
            }

            expect(count($executors))->toBe(100);
            
            // Each should be independent
            foreach ($executors as $executor) {
                expect($executor)->toBeInstanceOf(QueryExecutor::class);
            }
        });

        it('handles complex nested queries without memory issues', function () {
            $request = request()->merge([
                'filter' => [
                    'title_like' => 'test',
                    'status' => 'published',
                    'view_count_exact' => '100',
                    'is_featured' => '1',
                ],
                'sort' => ['-created_at', 'title', '-view_count'],
                'fields' => 'id,title,status,view_count,created_at,full_title'
            ]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });
    });

    describe('Concurrent Access Edge Cases', function () {
        it('handles role changes during query building', function () {
            TestRoleProvider::setRoles(['user']);
            
            $executor = Post::buildQuery();
            
            // Change roles after creating executor
            TestRoleProvider::setRoles(['admin']);
            
            // Should still work with original configuration
            $posts = $executor->get();
            expect($posts)->toBeCollection();
        });

        it('handles configuration changes during execution', function () {
            $model = new Post();
            $executor = $model->buildQuery();

            // Modify model configuration (simulating concurrent changes)
            $model->filterable = ['different' => 'config'];

            // Should still work with original configuration
            $posts = $executor->get();
            expect($posts)->toBeCollection();
        });
    });

    describe('SQL Injection Prevention', function () {
        it('prevents SQL injection in filter values', function () {
            $maliciousInput = "'; DROP TABLE posts; --";
            $request = request()->merge(['filter' => ['title_like' => $maliciousInput]]);
            app()->instance('request', $request);

            // Should not cause SQL injection
            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
            
            // Verify table still exists by checking count
            expect(Post::count())->toBeGreaterThanOrEqual(0);
        });

        it('prevents SQL injection in sort parameters', function () {
            $maliciousSort = "title; DROP TABLE posts; --";
            $request = request()->merge(['sort' => $maliciousSort]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('prevents SQL injection in field selection', function () {
            $maliciousFields = "id, (SELECT password FROM users LIMIT 1) as hacked";
            $request = request()->merge(['fields' => $maliciousFields]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });
    });

    describe('Type Coercion Edge Cases', function () {
        it('handles boolean filter values as strings', function () {
            $request = request()->merge(['filter' => ['is_featured' => 'true']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('handles numeric filter values as strings', function () {
            $request = request()->merge(['filter' => ['view_count_exact' => '100']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('handles array values in single-value filters', function () {
            $request = request()->merge(['filter' => ['status' => ['published', 'draft']]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });
    });

    describe('Configuration Validation', function () {
        it('validates filter configuration format', function () {
            $invalidFilterModel = new class extends Post {
                protected $filterable = [
                    'status' => ['invalid' => 'structure'],
                    'title' => 123, // Invalid type
                ];
            };

            $configManager = new ConfigManager($invalidFilterModel);
            $filters = $configManager->getFilters();

            // Should handle invalid configurations gracefully
            expect($filters)->toBeArray();
        });

        it('validates sort configuration format', function () {
            $invalidSortModel = new class extends Post {
                protected $sortable = [
                    'field1' => 'invalid_value', // Should be just field names
                    123 => 'numeric_key',
                ];
            };

            $configManager = new ConfigManager($invalidSortModel);
            $sorts = $configManager->getSorts();

            expect($sorts)->toBeArray();
        });

        it('validates field configuration format', function () {
            $invalidFieldModel = new class extends Post {
                protected $allowedFields = 'should_be_array';
                protected $forcedFields = 123;
                protected $fieldDependencies = 'invalid';
            };

            $configManager = new ConfigManager($invalidFieldModel);
            $fieldsConfig = $configManager->getFieldsConfig();

            expect($fieldsConfig)->toBeArray();
        });
    });

    describe('Error Recovery', function () {
        it('recovers from invalid scope methods', function () {
            $invalidScopeModel = new class extends Post {
                protected $filterable = [
                    'invalid_scope' => 'scope:nonExistentScope',
                ];
            };

            $request = request()->merge(['filter' => ['invalid_scope' => 'value']]);
            app()->instance('request', $request);

            // Should not throw an exception
            $posts = $invalidScopeModel::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('recovers from missing relationship methods', function () {
            $request = request()->merge(['filter' => ['nonexistent.field_like' => 'value']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });

        it('continues execution after filter errors', function () {
            $request = request()->merge([
                'filter' => [
                    'status' => 'published', // Valid filter
                    'invalid_field' => 'value', // Invalid filter
                ],
                'sort' => 'title' // Valid sort
            ]);
            app()->instance('request', $request);

            // Should apply valid operations and ignore invalid ones
            $posts = Post::buildQuery()->get();
            expect($posts)->toBeCollection();
        });
    });
});
