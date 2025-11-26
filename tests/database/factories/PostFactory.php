<?php

namespace Wever\AdvancedQueryBuilder\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'view_count' => $this->faker->numberBetween(0, 10000),
            'author_id' => User::factory(),
            'category_id' => Category::factory(),
            'published_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function withHighViews(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => $this->faker->numberBetween(5000, 50000),
        ]);
    }
}
