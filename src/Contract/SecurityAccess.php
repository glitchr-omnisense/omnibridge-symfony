<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Access;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Omnibridge\Contract\Access under Symfony: Security's authorization checker
 * (the role hierarchy, the application's voters) and token storage - the
 * answers of Twig's is_granted() and app.user.
 *
 * Moved from omniscient/omnibase's Base\Bridge\Symfony\SecurityAccess (plan
 * "omnibase agnostique", A1); the former name is an alias of this class.
 */
final class SecurityAccess implements Access
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }

    public function getUser(): ?object
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
