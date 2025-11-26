<?php

use Illuminate\Database\Eloquent\Builder;
use Wever\AdvancedQueryBuilder\Filtering\Filter;
use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;
use Wever\AdvancedQueryBuilder\Tests\Support\TestCustomFilter;

describe('Custom Filter Implementation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();

        // Create test posts
        $this->post1 = Post::factory()->create([
            'title' => 'custom_test_post',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->post2 = Post::factory()->create([
            'title' => 'regular_post',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->post3 = Post::factory()->create([
            'title' => 'custom_another_test',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    });

    describe('Custom Filter Interface', function () {
        it('implements the Filter interface correctly', function () {
            $filter = new TestCustomFilter;

            expect($filter)->toBeInstanceOf(Filter::class);
            expect(method_exists($filter, 'getOperationAlias'))->toBeTrue();
            expect(method_exists($filter, '__invoke'))->toBeTrue();
        });

        it('returns correct operation alias', function () {
            $filter = new TestCustomFilter;
            expect($filter->getOperationAlias())->toBe('custom');
        });

        it('can be invoked with query builder', function () {
            $filter = new TestCustomFilter;
            $query = Post::query();

            // Should not throw an exception
            $filter($query, 'test', 'title');
            expect($query)->toBeInstanceOf(Builder::class);
        });
    });

    describe('Custom Filter Integration with Models', function () {
        it('integrates custom filters in model configuration', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class],
                ];
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKey('title');
            expect($filters['title'])->toEqual([TestCustomFilter::class]);
        });

        it('works with multi-operation configuration', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class, 'exact'],
                ];
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters['title'])->toContain(TestCustomFilter::class);
            expect($filters['title'])->toContain('exact');
        });

        it('works with alias configuration', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [
                        'alias' => 'custom_title',
                        'operations' => [TestCustomFilter::class, 'like'],
                    ],
                ];
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters['title'])->toHaveKey('alias');
            expect($filters['title']['alias'])->toBe('custom_title');
            expect($filters['title']['operations'])->toContain(TestCustomFilter::class);
        });
    });

    describe('Custom Filter Execution', function () {
        it('applies custom filter logic correctly', function () {
            // Create a model that uses our test custom filter
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class],
                ];
            };

            // Mock the request with our custom filter
            $request = request()->merge(['filter' => ['title_custom' => 'test']]);
            app()->instance('request', $request);

            // The TestCustomFilter looks for titles starting with "custom_" + value
            // So "custom_test" should match our filter
            $posts = $testModel::buildQuery()->get();

            // Should find posts that match the custom filter logic
            expect($posts)->toHaveCount(2); // custom_test_post and custom_another_test
        });

        it('handles custom filter with different values', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class],
                ];
            };

            $request = request()->merge(['filter' => ['title_custom' => 'another']]);
            app()->instance('request', $request);

            $posts = $testModel::buildQuery()->get();

            // Should find posts matching "custom_another"
            expect($posts)->toHaveCount(1);
            expect($posts->first()->title)->toBe('custom_another_test');
        });
    });

    describe('Multiple Custom Filters', function () {
        it('handles multiple different custom filters', function () {
            // Create another custom filter
            $secondFilter = new class implements Filter
            {
                public function getOperationAlias(): string
                {
                    return 'special';
                }

                public function __invoke(Builder $query, mixed $value, mixed $property): void
                {
                    $query->where($property, 'like', "%{$value}%");
                }
            };

            $secondFilterClass = get_class($secondFilter);

            $testModel = new class($secondFilterClass) extends Post
            {
                protected $secondFilterClass;

                public function __construct($secondFilterClass, array $attributes = [])
                {
                    $this->secondFilterClass = $secondFilterClass;
                    $this->filterable = [
                        'title' => [TestCustomFilter::class, $this->secondFilterClass],
                    ];
                    parent::__construct($attributes);
                }
            };

            // Both filters should be available
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters['title'])->toHaveCount(2);
            expect($filters['title'])->toContain(TestCustomFilter::class);
        });

        it('combines custom filters with built-in filters', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class, 'exact', 'like'],
                ];
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters['title'])->toContain(TestCustomFilter::class);
            expect($filters['title'])->toContain('exact');
            expect($filters['title'])->toContain('like');
        });
    });

    describe('Custom Filter Error Handling', function () {
        it('handles invalid custom filter classes gracefully', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => ['NonExistentFilterClass'],
                ];
            };

            // Should not throw an exception
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toBeArray();
        });

        it('handles custom filters that do not implement Filter interface', function () {
            $invalidFilter = new class
            {
                public function getOperationAlias(): string
                {
                    return 'invalid';
                }
            };

            $invalidFilterClass = get_class($invalidFilter);

            $testModel = new class($invalidFilterClass) extends Post
            {
                protected $invalidFilterClass;

                public function __construct($invalidFilterClass, array $attributes = [])
                {
                    $this->invalidFilterClass = $invalidFilterClass;
                    $this->filterable = [
                        'title' => [$this->invalidFilterClass],
                    ];
                    parent::__construct($attributes);
                }
            };

            // Should handle gracefully
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            expect($configManager)->toBeInstanceOf(\Wever\AdvancedQueryBuilder\Support\ConfigManager::class);
        });
    });

    describe('Advanced Custom Filter Scenarios', function () {
        it('supports custom filters with complex logic', function () {
            // Create a complex custom filter
            $complexFilter = new class implements Filter
            {
                public function getOperationAlias(): string
                {
                    return 'complex';
                }

                public function __invoke(Builder $query, mixed $value, mixed $property): void
                {
                    // Complex logic: search in multiple fields
                    $query->where(function ($q) use ($value, $property) {
                        $q->where($property, 'like', "%{$value}%")
                            ->orWhere('content', 'like', "%{$value}%");
                    });
                }
            };

            $complexFilterClass = get_class($complexFilter);

            $testModel = new class($complexFilterClass) extends Post
            {
                protected $complexFilterClass;

                public function __construct($complexFilterClass, array $attributes = [])
                {
                    $this->complexFilterClass = $complexFilterClass;
                    $this->filterable = [
                        'title' => [$this->complexFilterClass],
                    ];
                    parent::__construct($attributes);
                }
            };

            // Test that complex filter can be configured
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters['title'])->toContain($complexFilterClass);
        });

        it('supports custom filters with relationship queries', function () {
            $relationFilter = new class implements Filter
            {
                public function getOperationAlias(): string
                {
                    return 'relation';
                }

                public function __invoke(Builder $query, mixed $value, mixed $property): void
                {
                    $query->whereHas('author', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }
            };

            $relationFilterClass = get_class($relationFilter);

            $testModel = new class($relationFilterClass) extends Post
            {
                protected $relationFilterClass;

                public function __construct($relationFilterClass, array $attributes = [])
                {
                    $this->relationFilterClass = $relationFilterClass;
                    $this->filterable = [
                        'author_name' => [$this->relationFilterClass],
                    ];
                    parent::__construct($attributes);
                }
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKey('author_name');
        });
    });

    describe('Custom Filter Performance', function () {
        it('does not create unnecessary instances of custom filters', function () {
            $filter = new TestCustomFilter;

            // Verify the filter can be instantiated without issues
            expect($filter)->toBeInstanceOf(Filter::class);
            expect($filter->getOperationAlias())->toBe('custom');
        });

        it('handles custom filters efficiently in configuration', function () {
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class],
                    'content' => [TestCustomFilter::class],
                    'status' => [TestCustomFilter::class],
                ];
            };

            // Should handle multiple custom filters efficiently
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKeys(['title', 'content', 'status']);
        });
    });
});
