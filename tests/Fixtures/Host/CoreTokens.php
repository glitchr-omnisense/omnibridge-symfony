<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Omnibridge\Symfony\Omnibase\CoreAccessTokens;

/** omnibase's Bearer tokens in the test: the one it issued is "core-token" (its repository stands aside). */
final class CoreTokens extends CoreAccessTokens
{
    protected function issued(string $token): bool
    {
        return 'core-token' === $token;
    }
}
