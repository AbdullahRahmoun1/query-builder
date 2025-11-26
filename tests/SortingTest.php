<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;

describe('Sorting Functionality', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();

        // Create posts with different values for sorting
        $this->oldPost = Post::factory()->create([
            'title' => 'Alpha Post',
            'view_count' => 50,
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
            'created_at' => now()->subDays(5),
            'published_at' => now()->subDays(3),
        ]);

        $this->newPost = Post::factory()->create([
            'title' => 'Beta Post',
            'view_count' => 150,
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
            'created_at' => now()->subDays(1),
            'published_at' => now()->subHours(12),
        ]);

        $this->middlePost = Post::factory()->create([
            'title' => 'Gamma Post',
            'view_count' => 100,
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
            'created_at' => now()->subDays(3),
            'published_at' => now()->subDays(1),
        ]);
    });

    describe('Basic Sorting', function () {
        it('sorts by title ascending by default', function () {
            $request = request()->merge(['sort' => 'title']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->title)->toBe('Alpha Post');
            expect($posts->last()->title)->toBe('Gamma Post');
        });

        it('sorts by title descending when specified', function () {
            $request = request()->merge(['sort' => '-title']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->title)->toBe('Gamma Post');
            expect($posts->last()->title)->toBe('Alpha Post');
        });

        it('sorts by created_at ascending', function () {
            $request = request()->merge(['sort' => 'created_at']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->id)->toBe($this->oldPost->id);
            expect($posts->last()->id)->toBe($this->newPost->id);
        });

        it('sorts by created_at descending', function () {
            $request = request()->merge(['sort' => '-created_at']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->id)->toBe($this->newPost->id);
            expect($posts->last()->id)->toBe($this->oldPost->id);
        });

        it('sorts by view_count', function () {
            $request = request()->merge(['sort' => 'view_count']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->view_count)->toBe(50);
            expect($posts->last()->view_count)->toBe(150);
        });

        it('sorts by published_at', function () {
            $request = request()->merge(['sort' => 'published_at']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);
            expect($posts->first()->id)->toBe($this->oldPost->id);
            expect($posts->last()->id)->toBe($this->newPost->id);
        });
    });

    describe('Multiple Sorting', function () {
        it('sorts by multiple fields', function () {
            // Create posts with same view count but different titles
            Post::factory()->create([
                'title' => 'Zulu Post',
                'view_count' => 100,
                'author_id' => $this->user->id,
                'category_id' => $this->category->id,
            ]);

            $request = request()->merge(['sort' => ['view_count', 'title']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(4);

            // First should be lowest view count
            expect($posts->first()->view_count)->toBe(50);

            // Among posts with view_count 100, should be sorted by title
            $hundredViewPosts = $posts->where('view_count', 100);
            expect($hundredViewPosts->first()->title)->toBe('Gamma Post');
        });

        it('handles mixed ascending and descending sorts', function () {
            $request = request()->merge(['sort' => ['-view_count', 'title']]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(3);

            // First should be highest view count
            expect($posts->first()->view_count)->toBe(150);

            // Last should be lowest view count
            expect($posts->last()->view_count)->toBe(50);
        });
    });

    describe('Sorting with Filtering', function () {
        it('combines sorting with filtering', function () {
            $request = request()->merge([
                'filter' => ['view_count_like' => '0'], // Posts with view count containing '0'
                'sort' => '-view_count',
            ]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();

            expect($posts)->toHaveCount(2); // 150 and 100 both contain '0'
            expect($posts->first()->view_count)->toBe(150);
            expect($posts->last()->view_count)->toBe(100);
        });
    });

    describe('Error Handling', function () {
        it('ignores invalid sort fields', function () {
            $request = request()->merge(['sort' => 'nonexistent_field']);
            app()->instance('request', $request);

            // Should not throw an exception
            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(3);
        });

        it('handles empty sort parameter', function () {
            $request = request()->merge(['sort' => '']);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(3);
        });

        it('handles null sort parameter', function () {
            $request = request()->merge(['sort' => null]);
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(3);
        });
    });

    describe('Sort Configuration', function () {
        it('only allows configured sortable fields', function () {
            // The Post model has specific sortable fields defined
            // Non-configured fields should be ignored
            $request = request()->merge(['sort' => 'content']); // Not in sortable array
            app()->instance('request', $request);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(3);
            // Should not be sorted by content since it's not in sortable array
        });

        it('allows all configured sortable fields', function () {
            // Test each configured sortable field
            $sortableFields = ['title', 'created_at', 'view_count', 'published_at'];

            foreach ($sortableFields as $field) {
                $request = request()->merge(['sort' => $field]);
                app()->instance('request', $request);

                $posts = Post::buildQuery()->get();
                expect($posts)->toHaveCount(3);
            }
        });
    });
});
