<?php

namespace Wever\AdvancedQueryBuilder\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConfigManager
{
    protected array $config = [];

    public function __construct(protected Model $model)
    {
        $this->loadAndMergeConfigs();
    }

    public function getFilters(): array
    {
        return $this->config['filters'] ?? [];
    }

    public function getSorts(): array
    {
        return $this->config['sorts'] ?? [];
    }

    public function getFieldsConfig(): array
    {
        return $this->config['fields'] ?? [];
    }

    protected function loadAndMergeConfigs(): void
    {
        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($this->model);
        $configClass = null;

        if ($reflection->hasProperty('queryBuilderConfig')) {
            $property = $reflection->getProperty('queryBuilderConfig');
            $property->setAccessible(true);
            $configClass = $property->getValue($this->model);
        }

        // Determine if we are using a dedicated config class or the model itself.
        if ($configClass && class_exists($configClass)) {
            // Use dedicated config class
            $configSource = new $configClass;
            $this->config = $this->loadFromConfigSource($configSource);
        } else {
            // Use model properties directly
            $this->config = $this->loadFromModelProperties();
        }
    }

    protected function loadFromConfigSource(object $configSource): array
    {
        // 1. Get the base configuration for everyone.
        $baseConfig = method_exists($configSource, 'getGeneralConfig')
            ? $configSource->getGeneralConfig()
            : []; // Empty base config if no getGeneralConfig method

        // 2. Get the current user's roles via the provider.
        $userRoles = [];
        try {
            $roleProviderClass = null;
            if (function_exists('config') && function_exists('app')) {
                try {
                    $roleProviderClass = config('query-builder.role_provider');
                } catch (\Exception $e) {
                    // Config not available
                }
            }

            if (! $roleProviderClass) {
                // Fallback to TestRoleProvider for testing
                $roleProviderClass = \Wever\AdvancedQueryBuilder\Tests\Support\TestRoleProvider::class;
            }

            if (class_exists($roleProviderClass)) {
                $userRoles = (new $roleProviderClass)->getUserRoles();
            }
        } catch (\Exception $e) {
            // Fallback when role provider is not available
            $userRoles = [];
        }

        $roleConfig = [];
        foreach ($userRoles as $role) {
            // 3. Look for a role-specific method, e.g., getAdminConfig()
            $methodName = 'get'.Str::studly($role).'Config';
            if (method_exists($configSource, $methodName)) {
                $roleConfig = $configSource->{$methodName}();
                break; // Use the first matching role config found.
            }
        }

        // 4. Deep merge the configs. Keys from the role config will overwrite keys from the general config.
        $mergedConfig = $this->deepMergeConfig($baseConfig, $roleConfig);

        return $mergedConfig;
    }

    /**
     * Deep merge configuration arrays, properly handling nested arrays
     */
    protected function deepMergeConfig(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                // For nested arrays, merge them recursively
                $result[$key] = array_merge($result[$key], $value);
            } else {
                // For scalar values or when base doesn't have the key, just override
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function loadFromModelProperties(): array
    {
        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($this->model);

        $filters = [];
        if ($reflection->hasProperty('filterable')) {
            $property = $reflection->getProperty('filterable');
            $property->setAccessible(true);
            $filters = $property->getValue($this->model) ?? [];
        }

        // Ensure $filters is always an array
        if (! is_array($filters)) {
            $filters = [];
        }

        // --- NEW "MAGIC" SCOPE DETECTION LOGIC ---
        $processedFilters = [];
        foreach ($filters as $key => $config) {
            // Check for the ['published'] format
            if (is_numeric($key) && is_string($config)) {
                $scopeMethodName = 'scope'.Str::studly($config);
                // If a scope exists, convert the config to the explicit format.
                if (method_exists($this->model, $scopeMethodName)) {
                    $processedFilters[$config] = 'scope';
                } else {
                    // Keep the original config if scope doesn't exist
                    $processedFilters[$config] = $config;
                }
            } else {
                // Keep the original config if it's not a magic scope format
                $processedFilters[$key] = $config;
            }
        }
        // Use reflection for other protected properties too
        $sorts = [];
        if ($reflection->hasProperty('sortable')) {
            $property = $reflection->getProperty('sortable');
            $property->setAccessible(true);
            $sorts = $property->getValue($this->model) ?? [];
        }

        $allowedFields = null;
        if ($reflection->hasProperty('allowedFields')) {
            $property = $reflection->getProperty('allowedFields');
            $property->setAccessible(true);
            $allowedFields = $property->getValue($this->model);
        }

        $hiddenFields = [];
        if ($reflection->hasProperty('hiddenFields')) {
            $property = $reflection->getProperty('hiddenFields');
            $property->setAccessible(true);
            $hiddenFields = $property->getValue($this->model) ?? [];
        }

        $forcedFields = [];
        if ($reflection->hasProperty('forcedFields')) {
            $property = $reflection->getProperty('forcedFields');
            $property->setAccessible(true);
            $forcedFields = $property->getValue($this->model) ?? [];
        }

        $fieldDependencies = [];
        if ($reflection->hasProperty('fieldDependencies')) {
            $property = $reflection->getProperty('fieldDependencies');
            $property->setAccessible(true);
            $fieldDependencies = $property->getValue($this->model) ?? [];
        }

        return [
            'filters' => $processedFilters,
            'sorts' => $sorts,
            'fields' => [
                'allowed' => $allowedFields,
                'hidden' => $hiddenFields,
                'forced' => $forcedFields,
                'dependencies' => $fieldDependencies,
            ],
        ];
    }
}
