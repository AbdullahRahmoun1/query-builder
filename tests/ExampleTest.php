<?php

use Wever\AdvancedQueryBuilder\Query\QueryExecutor;
use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;

describe('HasAdvancedQueries Trait', function () {
    beforeEach(function () {
        // Create test data
        $this->user = User::factory()->create(['name' => 'John Doe']);
        $this->category = Category::factory()->create(['name' => 'Technology']);
        $this->post = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
            'view_count' => 100,
        ]);
    });

    it('adds buildQuery scope to models', function () {
        expect(Post::query())->toHaveMethod('buildQuery');
    });

    it('returns QueryExecutor from buildQuery scope', function () {
        $executor = Post::buildQuery();
        expect($executor)->toBeInstanceOf(QueryExecutor::class);
    });

    it('can execute basic queries through the executor', function () {
        $posts = Post::buildQuery()->get();
        expect($posts)->toHaveCount(1);
        expect($posts->first()->title)->toBe('Test Post');
    });

    it('passes through eloquent methods to the underlying builder', function () {
        $count = Post::buildQuery()->count();
        expect($count)->toBe(1);

        $post = Post::buildQuery()->first();
        expect($post->title)->toBe('Test Post');
    });

    it('can chain multiple methods', function () {
        $posts = Post::buildQuery()
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();

        expect($posts)->toHaveCount(1);
    });
});
