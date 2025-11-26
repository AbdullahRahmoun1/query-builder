<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Category;
use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;
use Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider;

describe('Role-Based Configuration', function () {
    beforeEach(function () {
        TestRoleProvider::clearRoles();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();

        // Create test posts
        $this->publicPost = Post::factory()->create([
            'title' => 'Public Post',
            'status' => 'published',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->draftPost = Post::factory()->create([
            'title' => 'Draft Post',
            'status' => 'draft',
            'author_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);
    });

    afterEach(function () {
        TestRoleProvider::clearRoles();
    });

    describe('Role Provider Integration', function () {
        it('uses default role provider when no roles are set', function () {
            TestRoleProvider::setRoles([]);

            $posts = Post::buildQuery()->get();
            expect($posts)->toHaveCount(2); // Should see all posts with default config
        });

        it('applies different configurations based on user roles', function () {
            // Test with admin role
            TestRoleProvider::setRoles(['admin']);

            // Create a model with role-based config
            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            // Admin should have access to additional filters
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKey('admin_only_filter');
        });

        it('applies user role configuration', function () {
            TestRoleProvider::setRoles(['user']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->not->toHaveKey('admin_only_filter');
            expect($filters)->toHaveKey('user_filter');
        });

        it('handles multiple roles correctly', function () {
            TestRoleProvider::setRoles(['user', 'admin']); // User has multiple roles

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should use the first matching role (user in this case)
            expect($filters)->toHaveKey('user_filter');
        });
    });

    describe('Configuration Inheritance', function () {
        it('inherits general configuration in role-specific configs', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();

            // Should have both general and admin-specific config
            expect($filters)->toHaveKey('status'); // From general config
            expect($filters)->toHaveKey('admin_only_filter'); // From admin config
            expect($sorts)->toContain('created_at'); // From general config
        });

        it('allows role configs to override general configs', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfigWithOverrides::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Admin config should override general config
            expect($filters['status'])->toBe('like'); // Overridden by admin config
            expect($filters)->toHaveKey('admin_only_filter'); // Added by admin config
        });
    });

    describe('Dynamic Role Changes', function () {
        it('respects role changes during request', function () {
            // Start with user role
            TestRoleProvider::setRoles(['user']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager1 = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters1 = $configManager1->getFilters();

            // Change to admin role
            TestRoleProvider::setRoles(['admin']);

            $configManager2 = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters2 = $configManager2->getFilters();

            // Configurations should be different
            expect($filters1)->not->toEqual($filters2);
            expect($filters2)->toHaveKey('admin_only_filter');
            expect($filters1)->not->toHaveKey('admin_only_filter');
        });
    });

    describe('Field Selection by Role', function () {
        it('applies different field restrictions based on role', function () {
            TestRoleProvider::setRoles(['user']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $fieldsConfig = $configManager->getFieldsConfig();

            // Users should have restricted field access
            expect($fieldsConfig['allowed'])->not->toContain('internal_notes');
        });

        it('allows admins access to sensitive fields', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $fieldsConfig = $configManager->getFieldsConfig();

            // Admins should have access to sensitive fields
            expect($fieldsConfig['allowed'])->toContain('internal_notes');
        });
    });

    describe('Sorting Restrictions by Role', function () {
        it('restricts sorting options based on role', function () {
            TestRoleProvider::setRoles(['user']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $sorts = $configManager->getSorts();

            // Users should have limited sorting options
            expect($sorts)->not->toContain('internal_priority');
        });

        it('allows admins additional sorting options', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $sorts = $configManager->getSorts();

            // Admins should have additional sorting options
            expect($sorts)->toContain('internal_priority');
        });
    });

    describe('Error Handling', function () {
        it('handles non-existent role methods gracefully', function () {
            TestRoleProvider::setRoles(['nonexistent_role']);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            // Should not throw an exception
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should fall back to general config
            expect($filters)->toHaveKey('status');
        });

        it('handles empty role arrays', function () {
            TestRoleProvider::setRoles([]);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should use general config
            expect($filters)->toHaveKey('status');
        });

        it('handles null roles', function () {
            TestRoleProvider::setRoles([null]);

            $testModel = new class extends Post
            {
                protected string $queryBuilderConfig = RoleBasedPostConfig::class;
            };

            // Should not throw an exception
            $configManager = new \Wever\AdvancedQueryBuilder\Support\ConfigManager($testModel);
            expect($configManager)->toBeInstanceOf(\Wever\AdvancedQueryBuilder\Support\ConfigManager::class);
        });
    });
});

// Test configuration classes for role-based testing
class RoleBasedPostConfig
{
    public function getGeneralConfig(): array
    {
        return [
            'filters' => [
                'status' => 'exact',
                'title' => 'like',
            ],
            'sorts' => ['created_at', 'title'],
            'fields' => [
                'allowed' => ['id', 'title', 'status', 'created_at'],
                'forced' => ['id'],
            ],
        ];
    }

    public function getUserConfig(): array
    {
        return [
            'filters' => [
                'user_filter' => 'exact',
            ],
            'fields' => [
                'allowed' => ['id', 'title', 'status'], // Restricted fields for users
            ],
        ];
    }

    public function getAdminConfig(): array
    {
        return [
            'filters' => [
                'admin_only_filter' => 'exact',
                'internal_notes' => 'like',
            ],
            'sorts' => ['internal_priority'], // Additional sorts for admins
            'fields' => [
                'allowed' => ['id', 'title', 'status', 'created_at', 'internal_notes'], // More fields for admins
            ],
        ];
    }
}

class RoleBasedPostConfigWithOverrides
{
    public function getGeneralConfig(): array
    {
        return [
            'filters' => [
                'status' => 'exact',
                'title' => 'like',
            ],
        ];
    }

    public function getAdminConfig(): array
    {
        return [
            'filters' => [
                'status' => 'like', // Override general config
                'admin_only_filter' => 'exact',
            ],
        ];
    }
}
