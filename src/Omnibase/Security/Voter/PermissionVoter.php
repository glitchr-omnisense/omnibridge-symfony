<?php

namespace Omnibridge\Symfony\Omnibase\Security\Voter;

use Base\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Custom fine-grained permission names, backed by Base\Entity\User\Permission
 * and granted through group membership (or directly on the user). This is
 * the "customizable by base or app classes" extensibility a plain ROLE_*
 * hierarchy can't offer: base-bundle-admin's own SecurityVoter already
 * delegates every permission string through isGranted(), so registering
 * this voter is all that's needed for Actions::setPermission()/
 * Crud::setEntityPermission()/MenuItem::setPermission() to accept a custom
 * tag like "ARTICLE.PUBLISH" alongside a plain ROLE_* string - no changes
 * to base-bundle-admin required.
 */
class PermissionVoter extends Voter
{
    /**
     * Only claim the custom permission-name space - never ROLE_ or IS_
     * prefixed attributes (Symfony's own RoleHierarchyVoter territory) or
     * EA_ prefixed ones (base-bundle-admin's SecurityVoter territory,
     * checked by prefix rather than a direct class reference to avoid a
     * reverse dependency on omnibase/admin). A Voter returning
     * false here is harmless under Symfony's default "affirmative"
     * strategy - existing ROLE_-based authorization is provably unaffected.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return !str_starts_with($attribute, 'ROLE_')
            && !str_starts_with($attribute, 'IS_')
            && !str_starts_with($attribute, 'EA_')
            && 1 === preg_match('/^[A-Z][A-Z0-9_.:]*$/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        foreach ($this->tagsOf($user) as $tag) {
            if (0 === strcasecmp($tag, $attribute)) {
                return true;
            }

            // dotted namespacing: "ARTICLE.*" grants "ARTICLE.PUBLISH"
            if (str_ends_with($tag, '.*') && str_starts_with($attribute, substr($tag, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<string> the user's own Permission tags, plus every group's
     */
    private function tagsOf(User $user): iterable
    {
        foreach ($user->getPermissions() as $permission) {
            yield $permission->getTag();
        }

        foreach ($user->getGroups() as $group) {
            foreach ($group->getPermissions() as $permission) {
                yield $permission->getTag();
            }
        }
    }
}
