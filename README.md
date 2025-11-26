# wever/query-builder

An advanced, model-driven query builder for Laravel designed to provide a highly declarative and intuitive developer experience for creating complex APIs.

This package acts as a smart layer on top of the excellent `spatie/laravel-query-builder`, automating common patterns and introducing powerful features to dramatically speed up development. Its core philosophy is to allow developers to define all filtering, sorting, and field selection rules directly on their Eloquent models or in dedicated configuration classes, keeping controllers lean and code organized.

---

## Installation

You can install the package via composer:

```bash
composer require wever/query-builder
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Wever\AdvancedQueryBuilder\AdvancedQueryBuilderServiceProvider" --tag="query-builder-config"
```

This is the contents of the published config file:

```php
// config/query-builder.php
return [
    /**
     * The class responsible for providing the roles of the currently authenticated user.
     * This class must implement the Wever\AdvancedQueryBuilder\Auth\RoleProvider interface.
     */
    'role_provider' => \Wever\AdvancedQueryBuilder\Auth\DefaultRoleProvider::class,

    /**
     * A map of simple aliases to Spatie's internal filter methods.
     */
    'built_in_aliases' => [
        'exact' => 'exact',
        'like'  => 'partial',
        'scope' => 'scope',
    ],
];
```

## Basic Usage

The core of the package is the `HasAdvancedQueries` trait. Simply add it to your Eloquent model and define your query rules.

**1. Configure Your Model:**
```php
// app/Models/Post.php
use Wever\AdvancedQueryBuilder\Traits\HasAdvancedQueries;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasAdvancedQueries;

    // Define the rules for this model
    protected $filterable = [
        'status' => 'exact',
        'title' => ['like', 'exact'],
    ];

    protected $sortable = [
        'title',
        'created_at',
    ];
}
```

**2. Use the Scope in Your Controller:**
Use the `buildQuery()` scope to get a fully configured query builder instance.

```php
// app/Http/Controllers/PostController.php
class PostController
{
    public function index()
    {
        // All your filtering and sorting rules are applied automatically!
        return Post::buildQuery()->paginate();
    }
}
```

## Features

### Advanced Filtering

The package provides an incredibly flexible system for defining filters via the `$filterable` property on your model.

#### Filter Modes

**1. Simple Mode**
For quick, exact-match filtering.
- **Config:** `protected $filterable = ['status'];`
- **API Usage:** `?filter[status]=published`

**2. Simple Alias Mode**
Use any of the aliases defined in the `query-builder.php` config file.
- **Config:** `protected $filterable = ['title' => 'like'];`
- **API Usage:** `?filter[title]=Hello`

**3. Multi-Operation Mode**
Allow multiple filter operations on a single column. The package will automatically create unique filter names for the URL (`property_operation`).
- **Config:** `protected $filterable = ['view_count' => ['exact', 'like']];`
- **API Usage:** Allows both `?filter[view_count_exact]=100` and `?filter[view_count_like]=100`.

**4. Advanced Alias Mode**
For full control over URL naming and relational filters.
- **Config:** `'author.name' => ['alias' => 'author', 'operations' => ['like']]`
- **API Usage:** `?filter[author_like]=John`

#### Scope Filtering

Easily use your Eloquent scopes as filters.

**1. "Magic" Mode**
If a scope `scopePublished(Builder $query)` exists, just list its name. The package will find it automatically.
- **Config:** `protected $filterable = ['published'];`
- **API Usage:** `?filter[published]=true`

**2. Explicit Mode**
Directly tell the package to use a scope.
- **Config:** `protected $filterable = ['published' => 'scope'];`
- **API Usage:** `?filter[published]=true`

**3. Aliased Mode**
Map a clean URL parameter to a more complex scope that can accept values.
- **Config:** `protected $filterable = ['status' => 'scope:withStatus'];`
- **API Usage:** `?filter[status]=archived`

#### Custom Filters

For complex logic, you can create your own filter classes.

**1. Create a Filter Class:**
Your class must implement the `Wever\AdvancedQueryBuilder\Filtering\Filter` interface.
```php
// app/Filters/SoundexFilter.php
use Wever\AdvancedQueryBuilder\Filtering\Filter;
use Illuminate\Database\Eloquent\Builder;

class SoundexFilter implements Filter
{
    public function getOperationAlias(): string
    {
        return 'soundex'; // This will be used in the URL
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->whereRaw("SOUNDEX({$property}) = SOUNDEX(?)", [$value]);
    }
}
```

**2. Register it in Your Model:**
You can use your custom class in any of the advanced modes.
```php
// In your model
protected $filterable = [
    'title' => [SoundexFilter::class, 'exact'], // Multi-operation
    'author_name' => SoundexFilter::class,      // Single operation
];
```

### Advanced Field Selection

The package provides several "smart" features to make field selection robust and convenient.

**1. Automatic Field Discovery**
If no `$allowedFields` property is defined, the package will automatically scan your database table and allow all columns to be selected. You only need to define what to hide.
- **Config:** `protected $hiddenFields = ['password', 'remember_token'];`

**2. Forced Fields**
Ensure critical columns (like `id`) are always included in the query, even if not requested.
- **Config:** `protected $forcedFields = ['id', 'slug'];`

**3. Field Dependencies**
Automatically select the database columns that a computed attribute (accessor) depends on. This prevents bugs where accessors would fail silently.
- **Config:** `protected $fieldDependencies = ['full_name' => ['first_name', 'last_name']];`

### Context-Aware Configuration

For complex applications, you can define different query rules for different user roles.

**1. Create a Configuration Class:**
Organize all your rules into a dedicated class.
```php
// app/QueryConfigs/PostQueryConfig.php
class PostQueryConfig
{
    // Base rules for everyone
    public function getGeneralConfig(): array
    {
        return [
            'filters' => ['status' => 'exact'],
            'sorts'   => ['created_at'],
        ];
    }

    // Rules for users with the 'admin' role
    public function getAdminConfig(): array
    {
        // This 'filters' array will REPLACE the general one.
        // The 'sorts' array will be inherited from the general config.
        return [
            'filters' => ['is_banned' => 'scope', 'internal_notes' => 'like'],
        ];
    }
}
```

**2. Attach it to Your Model:**
```php
// app/Models/Post.php
class Post extends Model
{
    use HasAdvancedQueries;

    protected string $queryBuilderConfig = \App\QueryConfigs\PostQueryConfig::class;
}
```

**3. Provide a Role Handler (Optional):**
The package comes with a default role handler. If your application has a custom permission system, you can provide your own by implementing the `RoleProvider` interface and updating the `query-builder.php` config file.

## Future Enhancements
- Automatic Query Optimization
- Recursive/Nested Filtering
- An "Inspector" Artisan Command
