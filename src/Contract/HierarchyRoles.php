<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Roles;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Omnibridge\Contract\Roles under Symfony: Security's role hierarchy
 * (security.yaml's role_hierarchy), the roles given and those they reach.
 */
final class HierarchyRoles implements Roles
{
    public function __construct(private readonly RoleHierarchyInterface $hierarchy)
    {
    }

    public function reachable(array $roles): array
    {
        return array_values($this->hierarchy->getReachableRoleNames($roles));
    }
}
