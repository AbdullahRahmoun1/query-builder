<?php

use Wever\AdvancedQueryBuilder\Tests\Models\Post;
use Wever\AdvancedQueryBuilder\Tests\Models\User;
use Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider;
use Wever\AdvancedQueryBuilder\Support\ConfigManager;

describe('ConfigManager', function () {
    beforeEach(function () {
        TestRoleProvider::clearRoles();
    });

    afterEach(function () {
        TestRoleProvider::clearRoles();
    });

    describe('Basic Configuration Loading', function () {
        it('loads configuration from model properties', function () {
            $post = new Post();
            $configManager = new ConfigManager($post);

            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();
            $fieldsConfig = $configManager->getFieldsConfig();

            expect($filters)->toBeArray();
            expect($filters)->toHaveKey('status');
            expect($filters['status'])->toBe('exact');

            expect($sorts)->toBeArray();
            expect($sorts)->toContain('title');
            expect($sorts)->toContain('created_at');

            expect($fieldsConfig)->toBeArray();
            expect($fieldsConfig)->toHaveKey('allowed');
            expect($fieldsConfig)->toHaveKey('forced');
            expect($fieldsConfig)->toHaveKey('dependencies');
        });

        it('handles models without configuration gracefully', function () {
            $user = new User();
            $configManager = new ConfigManager($user);

            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();

            expect($filters)->toBeArray();
            expect($sorts)->toBeArray();
        });
    });

    describe('Magic Scope Detection', function () {
        it('detects and converts magic scopes automatically', function () {
            // Create a test model with magic scope
            $testModel = new class extends Post {
                protected $filterable = [
                    'published', // Should be detected as scope
                    'status' => 'exact', // Regular filter
                ];

                public function scopePublished($query)
                {
                    return $query->where('status', 'published');
                }
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKey('published');
            expect($filters['published'])->toBe('scope');
            expect($filters)->toHaveKey('status');
            expect($filters['status'])->toBe('exact');
        });

        it('does not convert non-existent scopes', function () {
            $testModel = new class extends Post {
                protected $filterable = [
                    'nonexistent', // No scopeNonexistent method exists
                ];
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should keep original config since scope doesn't exist
            expect($filters)->toHaveKey('nonexistent');
            expect($filters['nonexistent'])->not->toBe('scope');
        });
    });

    describe('Role-Based Configuration', function () {
        it('uses general config when no roles are set', function () {
            TestRoleProvider::setRoles([]);

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            expect($filters)->toHaveKey('status');
            expect($filters['status'])->toBe('exact');
        });

        it('merges role-specific config with general config', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should have admin-specific filters
            expect($filters)->toHaveKey('internal_notes');
            expect($filters['internal_notes'])->toBe('like');
        });

        it('uses first matching role config', function () {
            TestRoleProvider::setRoles(['admin', 'editor']); // Multiple roles

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should use admin config (first match)
            expect($filters)->toHaveKey('internal_notes');
        });

        it('falls back to general config when no role methods exist', function () {
            TestRoleProvider::setRoles(['nonexistent_role']);

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should use general config
            expect($filters)->toHaveKey('status');
            expect($filters['status'])->toBe('exact');
        });
    });

    describe('Configuration Class Integration', function () {
        it('uses dedicated configuration class when specified', function () {
            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();

            expect($filters)->toHaveKey('status');
            expect($sorts)->toContain('created_at');
        });

        it('falls back to model properties when config class does not exist', function () {
            $testModel = new class extends Post {
                protected string $queryBuilderConfig = 'NonExistentConfigClass';
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Should fall back to model properties
            expect($filters)->toBeArray();
        });
    });

    describe('Configuration Merging', function () {
        it('properly merges arrays from different config sources', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfig::class;
            };

            $configManager = new ConfigManager($testModel);
            $config = $configManager->getFilters();

            // Should have both general and admin-specific filters
            expect($config)->toBeArray();
            expect(count($config))->toBeGreaterThan(1);
        });

        it('overwrites general config with role-specific config for same keys', function () {
            TestRoleProvider::setRoles(['admin']);

            $testModel = new class extends Post {
                protected string $queryBuilderConfig = TestPostConfigWithOverrides::class;
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();

            // Admin config should override general config for 'status'
            expect($filters['status'])->toBe('like'); // Admin override
        });
    });

    describe('Error Handling', function () {
        it('handles missing properties gracefully', function () {
            $testModel = new class extends Post {
                // Override parent properties with empty arrays
                protected $filterable = [];
                protected $sortable = [];
                protected $allowedFields = [];
                protected $forcedFields = [];
                protected $fieldDependencies = [];
            };

            $configManager = new ConfigManager($testModel);
            $filters = $configManager->getFilters();
            $sorts = $configManager->getSorts();

            expect($filters)->toBeArray();
            expect($sorts)->toBeArray();
            expect($filters)->toBeEmpty();
            expect($sorts)->toBeEmpty();
        });

        it('handles invalid configuration gracefully', function () {
            $testModel = new class extends Post {
                protected $filterable = 'invalid_string'; // Should be array
            };

            // Should not throw an exception
            $configManager = new ConfigManager($testModel);
            expect($configManager)->toBeInstanceOf(ConfigManager::class);
        });
    });
});

// Test configuration classes
class TestPostConfig
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
                'dependencies' => [],
            ],
        ];
    }

    public function getAdminConfig(): array
    {
        return [
            'filters' => [
                'internal_notes' => 'like',
                'is_banned' => 'scope',
            ],
        ];
    }

    public function getEditorConfig(): array
    {
        return [
            'filters' => [
                'draft_notes' => 'like',
            ],
        ];
    }
}

class TestPostConfigWithOverrides
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
                'internal_notes' => 'like',
            ],
        ];
    }
}
