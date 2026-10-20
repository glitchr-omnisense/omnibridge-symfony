<?php

namespace Omnibridge\Symfony\Auth;

use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Security's trust resolver, for which the visitor between two factors
 * (PendingToken) is never fully signed in: IS_AUTHENTICATED_FULLY is refused
 * to it, as scheb/2fa's resolver refuses it to its TwoFactorToken.
 */
final class PendingTrustResolver implements AuthenticationTrustResolverInterface
{
    public function __construct(private readonly AuthenticationTrustResolverInterface $inner)
    {
    }

    public function isAuthenticated(?TokenInterface $token = null): bool
    {
        return $this->inner->isAuthenticated($token);
    }

    public function isRememberMe(?TokenInterface $token = null): bool
    {
        return $this->inner->isRememberMe($token);
    }

    public function isFullFledged(?TokenInterface $token = null): bool
    {
        return !$token instanceof PendingToken && $this->inner->isFullFledged($token);
    }
}
