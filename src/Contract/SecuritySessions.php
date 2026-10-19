<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Sessions;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Omnibridge\Contract\Sessions under Symfony: Security's token storage - the
 * token forgotten when its user is the one signed out - and the REMEMBERME
 * cookie cleared on the path and, when the application says which, on its
 * domain ($domain, asked at sign-out: omnibase gives its router's domain).
 *
 * Moved from omniscient/omnibase's Base\Bridge\Symfony\SecuritySessions
 * (plan "omnibase agnostique", A1), which took omnibase's router for that
 * domain; the former name is an alias of this class.
 */
final class SecuritySessions implements Sessions
{
    /**
     * @param (\Closure(): ?string)|null $domain the domain of the remember-me cookie
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?\Closure $domain = null,
    ) {
    }

    public function signOut(object $user): bool
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || $token->getUser() !== $user) {
            return false;
        }

        $this->tokenStorage->setToken(null);
        setcookie('REMEMBERME', '', time() - 1);
        setcookie('REMEMBERME', '', time() - 1, '/', (string) (null !== $this->domain ? ($this->domain)() : null));

        return true;
    }
}
