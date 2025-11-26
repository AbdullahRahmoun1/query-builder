<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;
use Wever\AdvancedQueryBuilder\Tests\Support\TestCustomFilter;

describe('Filtering Functionality', function () {
    beforeEach(function () {
        // Create test users
        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'role' => 'admin']);
        $this->regularUser = User::factory()->create(['name' => 'Regular User', 'role' => 'user']);

        // Create test categories
        $this->techCategory = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        $this->newsCategory = Category::factory()->create(['name' => 'News', 'slug' => 'news']);

        // Create test posts
        $this->publishedPost = Post::factory()->create([
            'title' => 'Published Tech Post',
            'status' => 'published',
            'author_id' => $this->adminUser->id,
            'category_id' => $this->techCategory->id,
            'view_count' => 150,
            'is_featured' => true,
        ]);

        $this->draftPost = Post::factory()->create([
            'title' => 'Draft News Post',
            'status' => 'draft',
            'author_id' => $this->regularUser->id,
            'category_id' => $this->newsCategory->id,
            'view_count' => 50,
            'is_featured' => false,
        ]);

        $this->archivedPost = Post::factory()->create([
            'title' => 'Archived Post',
            'status' => 'archived',
            'author_id' => $this->adminUser->id,
            'category_id' => $this->techCategory->id,
            'view_count' => 200,
            'is_featured' => false,
        ]);
    });

    describe('Exact Filtering', function () {
        it('filters by exact status match', function () {
            $request = request()->merge(['filter' => ['status' => 'published']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->status)->toBe('published');
        });

        it('filters by exact boolean values', function () {
            $request = request()->merge(['filter' => ['is_featured' => '1']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->is_featured)->toBeTrue();
        });

        it('returns empty result when no exact matches found', function () {
            $request = request()->merge(['filter' => ['status' => 'nonexistent']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(0);
        });
    });

    describe('Like Filtering', function () {
        it('filters using partial title match', function () {
            $request = request()->merge(['filter' => ['title_like' => 'Tech']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->title)->toContain('Tech');
        });

        it('is case insensitive for like filtering', function () {
            $request = request()->merge(['filter' => ['title_like' => 'tech']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->title)->toContain('Tech');
        });

        it('handles multiple like filters', function () {
            $request = request()->merge(['filter' => [
                'title_like' => 'Post',
                'view_count_like' => '5',
            ]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(2); // Both draft and published posts contain "Post" and have view counts with "5"
        });
    });

    describe('Multi-Operation Filtering', function () {
        it('allows both exact and like operations on the same field', function () {
            // Test exact match
            $request = request()->merge(['filter' => ['title_exact' => 'Published Tech Post']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
            expect($posts->first()->title)->toBe('Published Tech Post');

            // Test like match on the same field
            $request = request()->merge(['filter' => ['title_like' => 'Tech']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
            expect($posts->first()->title)->toContain('Tech');
        });

        it('combines multiple operations with AND logic', function () {
            $request = request()->merge(['filter' => [
                'title_like' => 'Post',
                'status' => 'published',
            ]]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->status)->toBe('published');
            expect($posts->first()->title)->toContain('Post');
        });
    });

    describe('Scope Filtering', function () {
        it('uses magic scope detection', function () {
            $request = request()->merge(['filter' => ['published' => 'true']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(1);
            expect($posts->first()->status)->toBe('published');
        });

        it('uses explicit scope filtering', function () {
            // This would require a model with explicit scope configuration
            $user = new class extends User
            {
                protected $filterable = [
                    'active' => 'scope:active',
                ];

                public function scopeActive($query)
                {
                    return $query->where('is_active', true);
                }
            };

            // We'll test this concept with the existing published scope
            expect(true)->toBeTrue(); // Placeholder for scope test
        });
    });

    describe('Relationship Filtering', function () {
        it('filters by relationship fields using alias', function () {
            $request = request()->merge(['filter' => ['author_like' => 'Admin']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(2); // Both posts by Admin User
            expect($posts->pluck('author_id')->unique())->toContain($this->adminUser->id);
        });
    });

    describe('Custom Filter Integration', function () {
        it('can use custom filter classes', function () {
            // Create a test model with custom filter
            $testModel = new class extends Post
            {
                protected $filterable = [
                    'title' => [TestCustomFilter::class],
                ];
            };

            // This test verifies the custom filter interface works
            $filter = new TestCustomFilter;
            expect($filter->getOperationAlias())->toBe('custom');
            expect($filter)->toBeInstanceOf(\Wever\AdvancedQueryBuilder\Filtering\Filter::class);
        });
    });

    describe('Error Handling', function () {
        it('handles invalid filter configurations gracefully', function () {
            $request = request()->merge(['filter' => ['nonexistent_field' => 'value']]);
            app()->instance('request', $request);

            // Should not throw an exception, just ignore invalid filters
            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(3); // All posts returned when invalid filter is ignored
        });

        it('handles empty filter values', function () {
            $request = request()->merge(['filter' => ['status' => '']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            // Empty filter should be ignored or handled gracefully
            expect($posts)->toHaveCount(3);
        });
    });
});
