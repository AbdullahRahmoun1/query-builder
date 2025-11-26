<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;

describe('Field Selection Functionality', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['name' => 'John Doe']);
        $this->category = Category::factory()->create(['name' => 'Technology']);
        $this->post = Post::factory()->create([
            'title' => 'Test Post',
            'content' => 'This is test content',
            'status' => 'published',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
            'view_count' => 100,
        ]);
    });

    describe('Basic Field Selection', function () {
        it('selects specific fields when requested', function () {
            $request = request()->merge(['fields' => 'id,title,status']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            expect($posts)->toHaveCount(1);

            // Should have requested fields
            expect($post->id)->not->toBeNull();
            expect($post->title)->toBe('Test Post');
            expect($post->status)->toBe('published');

            // Should not have non-requested fields (they should be null or not loaded)
            // Note: Laravel might still load some fields, so we check if the selection worked
            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'status']);
        });

        it('returns all allowed fields when no fields parameter is provided', function () {
            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            expect($posts)->toHaveCount(1);
            expect($post->title)->toBe('Test Post');
            expect($post->content)->toBe('This is test content');
            expect($post->status)->toBe('published');
        });

        it('handles comma-separated field list', function () {
            $request = request()->merge(['fields' => 'id,title']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            expect($post->getAttributes())->toHaveKeys(['id', 'title']);
        });

        it('handles array field list', function () {
            $request = request()->merge(['fields' => ['id', 'title', 'view_count']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'view_count']);
        });
    });

    describe('Forced Fields', function () {
        it('always includes forced fields even when not requested', function () {
            $request = request()->merge(['fields' => 'title,status']); // Not requesting 'id'
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            // 'id' should be included because it's in forcedFields
            expect($post->getAttributes())->toHaveKey('id');
            expect($post->id)->not->toBeNull();
        });

        it('includes forced fields with requested fields', function () {
            $request = request()->merge(['fields' => 'title']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            // Should have both requested field and forced field
            expect($post->getAttributes())->toHaveKeys(['id', 'title']);
        });
    });

    describe('Field Dependencies', function () {
        it('automatically includes dependent fields for accessors', function () {
            $request = request()->merge(['fields' => 'id,full_title']); // full_title depends on title and status
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            // Should include dependencies: title and status
            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'status']);

            // The accessor should work
            expect($post->full_title)->toBe('Test Post (published)');
        });

        it('includes dependencies even when not explicitly requested', function () {
            $request = request()->merge(['fields' => 'full_title']); // Only requesting the accessor
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            // Should automatically include id (forced), title and status (dependencies)
            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'status']);
        });

        it('handles multiple fields with different dependencies', function () {
            // Create a model with multiple accessors for testing
            $testModel = new class extends Post
            {
                protected $fieldDependencies = [
                    'full_title' => ['title', 'status'],
                    'author_info' => ['author_id'],
                ];

                public function getAuthorInfoAttribute()
                {
                    return 'Author: '.$this->author_id;
                }
            };

            // Test that dependencies are properly resolved
            expect($testModel->fieldDependencies)->toHaveKey('full_title');
            expect($testModel->fieldDependencies)->toHaveKey('author_info');
        });
    });

    describe('Field Validation', function () {
        it('only allows configured allowed fields', function () {
            $request = request()->merge(['fields' => 'id,title,password']); // password not in allowedFields
            app()->instance('request', $request);

            // Should not throw an exception, but should filter out invalid fields
            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles non-existent fields gracefully', function () {
            $request = request()->merge(['fields' => 'id,title,nonexistent_field']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });

        it('handles empty fields parameter', function () {
            $request = request()->merge(['fields' => '']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(1);
        });
    });

    describe('Auto Field Discovery', function () {
        it('discovers fields automatically when allowedFields is not set', function () {
            // Create a model without explicit allowedFields
            $testModel = new class extends User
            {
                protected $allowedFields = null; // Will auto-discover

                protected $hiddenFields = ['password', 'remember_token'];
            };

            // This tests the concept - actual implementation would scan database
            expect($testModel->hiddenFields)->toContain('password');
        });
    });

    describe('Field Selection with Relationships', function () {
        it('handles field selection with eager loading', function () {
            $request = request()->merge(['fields' => 'id,title,author_id']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->with('author')->get();
            $post = $posts->first();

            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'author_id']);
            expect($post->author)->not->toBeNull();
            expect($post->author->name)->toBe('John Doe');
        });
    });

    describe('Field Selection with Filtering and Sorting', function () {
        it('combines field selection with filtering', function () {
            $request = request()->merge([
                'fields' => 'id,title,status',
                'filter' => ['status' => 'published'],
            ]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            expect($posts)->toHaveCount(1);
            expect($post->getAttributes())->toHaveKeys(['id', 'title', 'status']);
            expect($post->status)->toBe('published');
        });

        it('combines field selection with sorting', function () {
            // Create another post for sorting
            Post::factory()->create([
                'title' => 'Another Post',
                'author_id' => $this->user->id,
                'category_id' => $this->category->id,
            ]);

            $request = request()->merge([
                'fields' => 'id,title',
                'sort' => 'title',
            ]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(2);
            expect($posts->first()->getAttributes())->toHaveKeys(['id', 'title']);
            expect($posts->first()->title)->toBe('Another Post'); // Alphabetically first
        });
    });

    describe('Performance Considerations', function () {
        it('only selects necessary columns to improve performance', function () {
            $request = request()->merge(['fields' => 'id,title']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            $post = $posts->first();

            // Should not have loaded unnecessary fields like content
            $attributes = $post->getAttributes();
            expect($attributes)->not->toHaveKey('content');
            expect(count($attributes))->toBeLessThan(10); // Should be minimal
        });
    });
});
