<?php

namespace Wever\AdvancedQueryBuilder\Auth;

class DefaultRoleProvider implements RoleProvider
{
    public function getUserRoles(): array
    {
        $user = auth()->user();

        if (! $user || ! isset($user->role)) {
            return [];
        }

        return is_array($user->role) ? $user->role : [$user->role];
    }
}
