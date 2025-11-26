<?php

namespace Wever\AdvancedQueryBuilder\Auth;

interface RoleProvider
{
    /**
     * @return string[] The roles of the current user.
     */
    public function getUserRoles(): array;
}