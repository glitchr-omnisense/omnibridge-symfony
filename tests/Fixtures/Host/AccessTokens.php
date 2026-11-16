<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/** Stands in the test for the service omnibase registers under the same id (HostConfiguration's constants). */
final class AccessTokens implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        // omnibase's token "core-token" is ada's; any other it was handed is refused, as the real handler refuses it.
        if ('core-token' !== $accessToken) {
            throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('Invalid credentials.');
        }

        return new UserBadge('ada');
    }
}
