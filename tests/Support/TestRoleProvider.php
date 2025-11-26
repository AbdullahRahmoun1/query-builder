<?php

namespace Wever\AdvancedQueryBuilder\Tests\Support;

use Wever\AdvancedQueryBuilder\Auth\RoleProvider;

class TestRoleProvider implements RoleProvider
{
    protected static array $roles = [];

    public function getUserRoles(): array
    {
        return static::$roles;
    }

    public static function setRoles(array $roles): void
    {
        static::$roles = $roles;
    }

    public static function clearRoles(): void
    {
        static::$roles = [];
    }
}
