<?php

namespace Wever\AdvancedQueryBuilder\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Wever\AdvancedQueryBuilder\Tests\Models\Comment;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_name' => $this->faker->name(),
            'content' => $this->faker->paragraph(),
            'is_approved' => $this->faker->boolean(70), // 70% chance of being approved
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }
}
