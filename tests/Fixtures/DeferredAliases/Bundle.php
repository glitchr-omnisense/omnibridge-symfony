<?php

namespace Omnibridge\Symfony\Tests\Fixtures\DeferredAliases;

use Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseBundle;

/** AbstractBaseBundle's deferred aliases, asked directly. */
final class Bundle extends AbstractBaseBundle
{
    public static function defer(string $class, string $alias, ?string $declared = null, bool $lookup = false): void
    {
        self::deferAlias($class, $alias, $declared, $lookup);
    }
}
