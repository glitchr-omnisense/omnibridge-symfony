<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\DefaultCachePoolPass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * omnibase's cache pool, `cache.adapter`, made on the application's default adapter where the application configures
 * none (omnibridge/parity's "What each host needs": a host without it did not compile), and left alone where it does.
 */
#[Group('without-omniauth')]
final class DefaultCachePoolPassTest extends TestCase
{
    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.app', (new Definition(FilesystemAdapter::class))->addTag('cache.pool'));

        return $container;
    }

    public function testThePoolIsMadeOnTheDefaultAdapterWhereThereIsNone(): void
    {
        $container = self::container();
        (new DefaultCachePoolPass())->process($container);

        $pool = $container->getDefinition(DefaultCachePoolPass::POOL);
        self::assertInstanceOf(ChildDefinition::class, $pool);
        self::assertSame('cache.app', $pool->getParent());
        self::assertTrue($pool->hasTag('cache.pool'), 'a pool: FrameworkBundle\'s CachePoolPass names and namespaces it');
        self::assertGreaterThan(32, DefaultCachePoolPass::getPriority(), 'before CachePoolPass');
    }

    public function testThePoolTheApplicationConfiguresIsKept(): void
    {
        $container = self::container();
        $own = (new Definition(ArrayAdapter::class))->addTag('cache.pool');
        $container->setDefinition('cache.adapter', $own);
        (new DefaultCachePoolPass())->process($container);

        self::assertSame($own, $container->getDefinition('cache.adapter'));
    }

    public function testNothingWithoutAnApplicationCache(): void
    {
        $container = new ContainerBuilder();
        (new DefaultCachePoolPass())->process($container);

        self::assertFalse($container->has('cache.adapter'));
    }
}
