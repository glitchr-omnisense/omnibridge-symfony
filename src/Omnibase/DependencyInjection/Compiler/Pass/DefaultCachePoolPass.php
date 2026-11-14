<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * omnibase's cache pool, `cache.adapter` (the router's compiled routes, the setting bag, the code caches cache:clear
 * empties), where the application configures none: a pool of that name on the application's default adapter
 * (cache.app's), as `framework.cache.pools.cache.adapter: ~` makes it. Until now a host without that pool did not
 * compile (Omnibridge\Symfony\Omnibase\Routing\AdvancedRouter's "non-existent service cache.adapter"; omnibridge/parity's "What each host
 * needs"). A pool the application configures is left as it is.
 *
 * Before FrameworkBundle's CachePoolPass (priority 32), which names and namespaces the pools.
 */
final class DefaultCachePoolPass implements CompilerPassInterface
{
    public const POOL = 'cache.adapter';

    public static function getPriority(): int
    {
        return 1024;
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(self::POOL) || !$container->has('cache.app')) {
            return;
        }
        $container->setDefinition(self::POOL, (new ChildDefinition('cache.app'))->addTag('cache.pool'));
    }
}
