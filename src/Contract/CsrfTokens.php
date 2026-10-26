<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Tokens;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Omnibridge\Contract\Tokens under Symfony: the CSRF token manager's token of
 * an intent - what Twig's `csrf_token()` prints -, and its check, the one
 * AbstractController::isCsrfTokenValid() makes.
 *
 * Moved from omnisense/omnibase's Base\Bridge\Symfony\CsrfTokens (plan
 * "omnibase agnostique", A1); the former name is an alias of this class.
 */
final class CsrfTokens implements Tokens
{
    public function __construct(private readonly CsrfTokenManagerInterface $manager)
    {
    }

    public function csrf(string $intent): string
    {
        return $this->manager->getToken($intent)->getValue();
    }

    public function isValid(string $intent, ?string $token): bool
    {
        return null !== $token && '' !== $token && $this->manager->isTokenValid(new CsrfToken($intent, $token));
    }
}
