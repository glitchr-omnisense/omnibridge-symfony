<?php

namespace Omnibridge\Symfony\Tests;

use Base\DependencyInjection\Compiler\DiscoveryPass;
use Base\Discovery;
use Omnibridge\Symfony\Tests\Fixtures\Package\PackageBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * DiscoveryPass's `entities` kind (3.2, omnisense/omnibase 7.2's Base\Discovery): a registered omnibase bundle's
 * src/Entity found with no configuration, the base.entity_folders a package adds and leaves out, in the parameter
 * omnibridge/eloquent describes from - and, where DoctrineBundle is registered, in its default entity manager's mapping
 * (what auto_mapping or a package's prepend maps already kept as it is), the folders left out excluded from every
 * attribute driver, the package's #[ResolveTargetEntity] interface given to the resolve_target_entities listener (what
 * doctrine.yaml resolves kept).
 */
final class EntitiesDiscoveryTest extends TestCase
{
    private const PACKAGE = __DIR__.'/Fixtures/Package';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(Discovery::class) || !\in_array('entities', Discovery::KINDS, true)) {
            self::markTestSkipped('omnisense/omnibase 7.2 declares the `entities` kind.');
        }
        // The fixture package's classes, as Composer autoloads a package's src/.
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Omnibridge\\Symfony\\Tests\\Fixtures\\Package\\';
            if (str_starts_with($class, $prefix) && is_file($file = self::PACKAGE.'/src/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php')) {
                require_once $file;
            }
        });
    }

    private static function container(bool $doctrine): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir().'/omnibridge-symfony-no-project');
        $container->setParameter('kernel.bundles', ['BaseBundle' => \Base\BaseBundle::class, 'PackageBundle' => PackageBundle::class, 'FrameworkBundle' => \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class]);
        $container->setParameter('kernel.bundles_metadata', [
            'PackageBundle' => ['path' => realpath(self::PACKAGE).'/src', 'namespace' => 'Omnibridge\Symfony\Tests\Fixtures\Package'],
            'FrameworkBundle' => ['path' => '/nowhere', 'namespace' => 'Symfony\Bundle\FrameworkBundle'],
        ]);
        $container->setParameter('base.entity_folders.include', []);
        $container->setParameter('base.entity_folders.exclude', [realpath(self::PACKAGE).'/src/Entity/Off']);
        if ($doctrine) {
            $container->setParameter('doctrine.default_entity_manager', 'default');
            $container->register('doctrine.orm.default_attribute_metadata_driver', 'Doctrine\ORM\Mapping\Driver\AttributeDriver')->setArguments([[\Base\Database\Schema\EntitySchema::coreDirectory('Entity')]]);
            $container->register('doctrine.orm.default_metadata_driver', 'Doctrine\Persistence\Mapping\Driver\MappingDriverChain')
                ->addMethodCall('addDriver', [new Reference('doctrine.orm.default_attribute_metadata_driver'), 'Base\Entity']);
            $container->register('doctrine.orm.listeners.resolve_target_entity', 'Doctrine\ORM\Tools\ResolveTargetEntityListener');
        }

        return $container;
    }

    public function testABundlesEntitiesWithoutDoctrine(): void
    {
        $container = self::container(false);
        (new DiscoveryPass(Discovery::registries(), ['entities']))->process($container);

        $entities = $container->getParameter(DiscoveryPass::ENTITIES);
        $package = realpath(self::PACKAGE);
        self::assertSame([\Base\Database\Schema\EntitySchema::coreDirectory('Entity') => 'Base\Entity', $package.'/src/Entity' => 'Omnibridge\Symfony\Tests\Fixtures\Package\Entity'], $entities['paths']);
        self::assertSame([$package => 'Omnibridge\Symfony\Tests\Fixtures\Package'], $entities['packages'], 'BaseBundle and a bundle that is no omnibase package left out');
        self::assertSame([$package.'/src/Entity/Off'], $entities['exclude']);
        self::assertSame(['Omnibridge\Symfony\Tests\Fixtures\Package\Model\OwnerInterface' => 'App\Entity\User'], $entities['resolve_target_entities']);
        self::assertFalse($container->hasDefinition(DiscoveryPass::DISCOVERED_DRIVER));

        $options = \Base\Console\InstalledEntities::eloquent('/project', $entities);
        self::assertSame(array_keys($entities['paths']), $options['paths'], 'what omnibridge/eloquent describes');
        self::assertSame($entities['exclude'], $options['exclude']);
    }

    public function testTheSameDiscoveryFeedsDoctrine(): void
    {
        $container = self::container(true);
        $container->getDefinition('doctrine.orm.listeners.resolve_target_entity')->addMethodCall('addResolveTargetEntity', ['Some\Interface', 'App\Entity\Some', []]);
        (new DiscoveryPass(Discovery::registries(), ['entities']))->process($container);

        $package = realpath(self::PACKAGE);
        $driver = $container->getDefinition(DiscoveryPass::DISCOVERED_DRIVER);
        self::assertSame([[$package.'/src/Entity']], $driver->getArguments(), 'the core, mapped already, is not mapped twice');
        self::assertEquals([
            ['addDriver', [new Reference('doctrine.orm.default_attribute_metadata_driver'), 'Base\Entity']],
            ['addDriver', [new Reference(DiscoveryPass::DISCOVERED_DRIVER), 'Omnibridge\Symfony\Tests\Fixtures\Package\Entity']],
        ], $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
        foreach (['doctrine.orm.default_attribute_metadata_driver', DiscoveryPass::DISCOVERED_DRIVER] as $id) {
            self::assertSame([['addExcludePaths', [[$package.'/src/Entity/Off']]]], $container->getDefinition($id)->getMethodCalls(), $id);
        }

        $listener = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');
        self::assertSame([
            ['addResolveTargetEntity', ['Some\Interface', 'App\Entity\Some', []]],
            ['addResolveTargetEntity', ['Omnibridge\Symfony\Tests\Fixtures\Package\Model\OwnerInterface', 'App\Entity\User', []]],
        ], $listener->getMethodCalls());
        self::assertSame([['event' => 'loadClassMetadata'], ['event' => 'onClassMetadataNotFound']], $listener->getTag('doctrine.event_listener'));
    }

    public function testWhatDoctrineMapsAlreadyIsKept(): void
    {
        $container = self::container(true);
        $package = realpath(self::PACKAGE);
        // A package's prepend or auto_mapping: its folder in DoctrineBundle's own driver.
        $container->getDefinition('doctrine.orm.default_attribute_metadata_driver')->setArguments([[\Base\Database\Schema\EntitySchema::coreDirectory('Entity'), $package.'/src/Entity']]);
        $container->getDefinition('doctrine.orm.listeners.resolve_target_entity')->addMethodCall('addResolveTargetEntity', ['Omnibridge\Symfony\Tests\Fixtures\Package\Model\OwnerInterface', 'App\Entity\Merchant', []])
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata'])->addTag('doctrine.event_listener', ['event' => 'onClassMetadataNotFound']);
        (new DiscoveryPass(Discovery::registries(), ['entities']))->process($container);

        self::assertFalse($container->hasDefinition(DiscoveryPass::DISCOVERED_DRIVER));
        self::assertCount(1, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
        self::assertSame([['addResolveTargetEntity', ['Omnibridge\Symfony\Tests\Fixtures\Package\Model\OwnerInterface', 'App\Entity\Merchant', []]]], $container->getDefinition('doctrine.orm.listeners.resolve_target_entity')->getMethodCalls(), 'doctrine.yaml\'s target prevails');
    }
}
