<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\DiscoveryPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The generic pass that compiles omnisense/omnibase's registries of discovery (Base\Discovery, its plan "omnibase
 * agnostic", step F5e slice 4c), on registries written here: each kind gives the container what the former dedicated
 * pass gave it - the collections' calls in the container's order, the fallback and the preferred alias, the arguments
 * from another package's parameters or definition, the local storages, the repository interfaces in DoctrineBundle's
 * locator - and the autoconfiguration tags a class.
 */
final class DiscoveryPassTest extends TestCase
{
    private const REGISTRIES = [
        'collect' => [
            ['service' => 'collector', 'method' => 'add', 'tag' => 'things'],
            ['service' => 'by.class', 'method' => 'add', 'instanceof' => \Countable::class],
            ['service' => 'nowhere', 'method' => 'add', 'tag' => 'things'],
        ],
        'fallbacks' => [
            'linker' => ['candidates' => ['linker.admin'], 'default' => \stdClass::class],
            'bound' => ['default' => \stdClass::class],
        ],
        'preferred' => ['texts.cache' => ['prefer' => 'cache.redis', 'over' => 'cache.app']],
        'arguments' => [
            ['service' => 'guard', 'argument' => '$challenge', 'parameter' => 'shield.gateway', 'type' => 'string', 'default' => null],
            ['service' => 'guard', 'argument' => '$unreachable', 'option' => 'shield.unreachable', 'type' => 'bool', 'symfony' => ['definition' => ['shield.validator', 3]]],
            ['service' => 'guard', 'argument' => '$google', 'parameter' => 'google.enable', 'resolve' => true, 'type' => 'bool', 'default' => false],
        ],
        'storages' => ['tag' => 'flysystem.storage', 'local' => ['local.cache' => '%kernel.project_dir%/var/cache', 'local.uploads' => '%kernel.project_dir%/var/uploads']],
        'repositories' => ['App\Repository\Things' => 'App\Repository\ThingRepository'],
    ];

    public function testEachKindIsCompiled(): void
    {
        $container = new ContainerBuilder();
        $container->register('collector', \ArrayObject::class);
        $container->register('by.class', \ArrayObject::class);
        $container->register('first', \stdClass::class)->addTag('things');
        $container->register('second', \stdClass::class)->addTag('things');
        $container->register(\ArrayIterator::class, \ArrayIterator::class);
        $container->register('bound', \ArrayObject::class);
        $container->register('cache.redis', \stdClass::class);
        $container->setAlias('texts.cache', 'cache.app');
        $container->register('guard', \stdClass::class);
        $container->register('shield.validator', \stdClass::class)->setArguments([1, 2, 'altcha', 1]);
        $container->setParameter('shield.gateway', 'altcha');
        $container->setParameter('google.raw', '1');
        $container->setParameter('google.enable', '%google.raw%');
        $container->register('uploads', \stdClass::class)->addTag('flysystem.storage', ['storage' => 'local.uploads']);
        $container->register('repository.locator', \stdClass::class)->setArguments([['App\Repository\ThingRepository' => new Reference('thing.repository')]]);
        $container->register('doctrine.orm.container_repository_factory', \stdClass::class)->setArguments([new Reference('repository.locator')]);

        foreach (DiscoveryPass::PRIORITIES as $kinds) {
            (new DiscoveryPass(self::REGISTRIES, $kinds))->process($container);
        }

        self::assertEquals([['add', [new Reference('first')]], ['add', [new Reference('second')]]], $container->getDefinition('collector')->getMethodCalls());
        self::assertEquals([['add', [new Reference(\ArrayIterator::class)]]], $container->getDefinition('by.class')->getMethodCalls(), 'the ids that are classes of the interface');
        self::assertFalse($container->has('nowhere'));
        self::assertSame(\stdClass::class, $container->getDefinition('linker')->getClass(), 'no candidate: the default');
        self::assertSame(\ArrayObject::class, $container->getDefinition('bound')->getClass(), 'a service bound stays');
        self::assertSame('cache.redis', (string) $container->getAlias('texts.cache'));
        self::assertSame(['$challenge' => 'altcha', '$unreachable' => true, '$google' => true], $container->getDefinition('guard')->getArguments());
        self::assertTrue($container->hasDefinition('local.cache'));
        self::assertSame([['storage' => 'local.cache']], $container->getDefinition('local.cache')->getTag('flysystem.storage'));
        self::assertSame(\stdClass::class, $container->getDefinition('uploads')->getClass(), 'a storage the application tags stays its own');
        self::assertFalse($container->hasDefinition('local.uploads'));
        self::assertEquals(['App\Repository\ThingRepository' => new Reference('thing.repository'), 'App\Repository\Things' => new Reference('thing.repository')], $container->getDefinition('repository.locator')->getArgument(0));

        $admin = new ContainerBuilder();
        $admin->register('linker.admin', \stdClass::class);
        $admin->register('guard', \stdClass::class);
        (new DiscoveryPass(self::REGISTRIES, ['fallbacks', 'arguments']))->process($admin);
        self::assertSame('linker.admin', (string) $admin->getAlias('linker'));
        self::assertSame(['$challenge' => null, '$google' => false], $admin->getDefinition('guard')->getArguments(), 'what the package does not say: the default, or left as it is');
    }

    public function testTheAutoconfigurationTagsAClassAndThePassRunsAtThreeMoments(): void
    {
        $container = new ContainerBuilder();
        DiscoveryPass::autoconfigure($container, [
            ['instanceof' => \Countable::class, 'tags' => ['countable', ['name' => 'listener', 'event' => 'saved']]],
            ['instanceof' => \Traversable::class, 'tags' => ['never'], 'requires' => ['Omnibridge\Symfony\Tests\NotInstalled']],
        ]);
        $instanceof = $container->getAutoconfiguredInstanceof();
        self::assertSame(['countable' => [[]], 'listener' => [['event' => 'saved']]], $instanceof[\Countable::class]->getTags());
        self::assertArrayNotHasKey(\Traversable::class, $instanceof);

        $builder = new ContainerBuilder();
        DiscoveryPass::register($builder, []);
        $passes = array_values(array_filter($builder->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof DiscoveryPass));
        self::assertCount(3, $passes);
    }
}
