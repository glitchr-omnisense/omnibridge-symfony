<?php

namespace Base\DependencyInjection\Compiler;

use Base\DependencyInjection\Compiler\Pass\EnumerationsPass;
use Doctrine\DBAL\Types\TypeRegistry;
use Omnibridge\Doctrine\Omnibase\Types;
use Omnibridge\Symfony\DependencyInjection\ContractsPass as HostContractsPass;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * omnibase's registries of discovery compiled into Symfony's container (plan "omnibase agnostic", step F5e slice 4c;
 * §2.5: the declaration is the core's data, this bridge compiles it). The core declares what is discovered among its
 * services (Base\Discovery::registries(), src/Resources/discovery.php); this one pass compiles every kind of it, where
 * fifteen passes each held their own list until then (their former code: Legacy\DependencyInjection\Compiler\Pass\*,
 * registered for a core without Base\Discovery):
 *
 * - `collect`: each tagged service (or each service whose id is a class that is one) handed to its collecting service by
 *   a method call, in the container's order (the former Attribute, EntityExtension, IconProvider, ObfuscatorCompression,
 *   Sharing, Trading and TagRenderer passes);
 * - `contracts`: the locator Base\Contract\Runtime reaches the contracts through, built by omnibridge/symfony's generic
 *   ContractsPass from the core's list (the former ContractsPass);
 * - `fallbacks`: a service no one binds aliased to the first candidate defined, else registered with its default class
 *   (AdminUrlGenerator); `preferred`: an alias moved to a better service where it is defined (TextOverrideCache);
 * - `arguments`: arguments read from another package's parameters, or from its definition where Symfony's container
 *   has it there (Guard); `texts`: the application's own texts read by the core's reader (ApplicationTexts);
 * - `storages`: the core's local storages where league/flysystem-bundle is not registered (FlysystemStorages);
 * - `enumerations`: taken out of DoctrineBundle's types and handed to omnibridge/doctrine's middleware - before
 *   DoctrineBundle's middleware pass (EnumerationTypes); `repositories`: the core's repository interfaces added to
 *   DoctrineBundle's repository locator - after its repository pass (RepositoryInterfaces);
 * - `entities` (3.2): the entity folders of the core, of every registered omnibase bundle (AbstractBaseBundle: its
 *   src/Entity, wherever its package is) and of the application, with the base.entity_folders a package or the
 *   application adds and leaves out, in the parameter ENTITIES - what omnibridge/eloquent describes - and, where
 *   DoctrineBundle is registered, in its default entity manager's mapping: the folders it does not map yet (a bundle
 *   auto_mapping or a package's prepend maps already is kept as it is) through one attribute driver of their own, the
 *   folders left out excluded from every attribute driver, the packages' #[ResolveTargetEntity] interfaces given to its
 *   resolve_target_entities listener (those doctrine.yaml resolves kept). A package needs no
 *   prependExtensionConfig('doctrine', ...) for its src/Entity any more.
 *
 * `autoconfigure` is compiled by BaseExtension::load() (autoconfigure()): Symfony reads it before any compiler pass.
 * What is Symfony's own knowledge stays here: when each kind runs (PRIORITIES), DoctrineBundle's and the flysystem
 * bundle's names.
 */
final class DiscoveryPass implements CompilerPassInterface
{
    /** The pass giving Doctrine's connections omnibase's types and entity managers the schema editor API (omnibridge/doctrine 4.0). */
    private const DOCTRINE = 'doctrine';

    /** The type provider of omnibase's connections (omnibridge/doctrine 4.0). */
    public const TYPE_PROVIDER = 'omnibridge.doctrine.type_provider';

    /**
     * The kinds compiled at each moment of Symfony's compilation: the enumerations before DoctrineBundle's
     * MiddlewaresPass (priority 0) collects the doctrine.middleware services, the entities before its
     * RegisterEventListenersAndSubscribersPass (priority 0) reads the listeners' tags, the repositories after its
     * ServiceRepositoryCompilerPass made the locator, the others where the former passes ran.
     */
    public const PRIORITIES = [
        10 => ['enumerations', 'entities'],
        0 => ['collect', 'contracts', 'fallbacks', 'preferred', 'arguments', 'texts', 'storages'],
        -10 => ['repositories'],
    ];

    /**
     * The entities discovered (the `entities` kind): `paths` (folder => namespace), `exclude` (files and folders left
     * out), `packages` (each registered omnibase bundle's folder => its namespace), `resolve_target_entities` (the
     * packages' default targets, interface => class) - EntitySchema's options, which EloquentHost describes.
     */
    public const ENTITIES = 'base.database.entities';

    /** The attribute driver of the folders DoctrineBundle did not map (`entities`). */
    public const DISCOVERED_DRIVER = 'omnibridge.doctrine.discovered_metadata_driver';

    /** The extension whose configuration names the storages itself (league/flysystem-bundle). */
    public const FLYSYSTEM_EXTENSION = 'flysystem';

    /**
     * @param array<string, mixed> $registries Base\Discovery::registries()
     * @param list<string>         $kinds      the kinds this instance compiles
     */
    public function __construct(private readonly array $registries, private readonly array $kinds)
    {
    }

    /** The pass at each moment its kinds run. */
    public static function register(ContainerBuilder $container, array $registries): void
    {
        foreach (self::PRIORITIES as $priority => $kinds) {
            $container->addCompilerPass(new self($registries, $kinds), priority: $priority);
        }
        // omnibridge/doctrine 4.0: the connections' type provider and the schema editor API, after DoctrineBundle's
        // RegisterDbalTypePass (-10), which gives each connection a provider of its own since DoctrineBundle 3.4.
        $container->addCompilerPass(new self($registries, [self::DOCTRINE]), priority: -20);
    }

    /**
     * The tags a service whose class is one gets (Symfony's registerForAutoconfiguration), an entry whose requirements
     * are not installed left out, in the registry's order.
     *
     * @param list<array{instanceof: string, tags: list<string|array<string, mixed>>, requires?: list<string>}> $autoconfigure
     */
    public static function autoconfigure(ContainerBuilder $container, array $autoconfigure): void
    {
        foreach ($autoconfigure as $entry) {
            foreach ($entry['requires'] ?? [] as $required) {
                if (!class_exists($required) && !interface_exists($required)) {
                    continue 2;
                }
            }
            $child = $container->registerForAutoconfiguration($entry['instanceof']);
            foreach ($entry['tags'] as $tag) {
                \is_string($tag) ? $child->addTag($tag) : $child->addTag($tag['name'], array_diff_key($tag, ['name' => true]));
            }
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if ([self::DOCTRINE] === $this->kinds) {
            $this->doctrine($container);

            return;
        }
        foreach ($this->kinds as $kind) {
            if (isset($this->registries[$kind])) {
                $this->{$kind}($container, $this->registries[$kind]);
            }
        }
    }

    /** @param list<array{service: string, method: string, tag?: string, instanceof?: string}> $collections */
    private function collect(ContainerBuilder $container, array $collections): void
    {
        foreach ($collections as $collection) {
            if (!$container->has($collection['service'])) {
                continue;
            }
            $definition = $container->findDefinition($collection['service']);
            if (isset($collection['tag'])) {
                foreach (array_keys($container->findTaggedServiceIds($collection['tag'])) as $id) {
                    $definition->addMethodCall($collection['method'], [new Reference($id)]);
                }
                continue;
            }
            foreach ($container->getDefinitions() as $id => $candidate) {
                // Symfony keeps an abstract definition, tagged container.excluded, for each file a resource's `exclude`
                // left out (a package's feature that is off): its class may stand on a package that is not installed,
                // and class_exists() would then load it into a fatal error. It is no service: not asked.
                if ($candidate->isAbstract() || $candidate->hasTag('container.excluded')) {
                    continue;
                }
                if (class_exists($id) && is_subclass_of($id, $collection['instanceof'])) {
                    $definition->addMethodCall($collection['method'], [new Reference($id)]);
                }
            }
        }
    }

    /** @param array{service: string, contracts: list<string>, services: array<string, string>, former: array<string, string>} $contracts */
    private function contracts(ContainerBuilder $container, array $contracts): void
    {
        (new HostContractsPass($contracts['contracts'], $contracts['services'], $contracts['former'], $contracts['service']))->process($container);
    }

    /** @param array<string, array{candidates?: list<string>, default: string}> $fallbacks */
    private function fallbacks(ContainerBuilder $container, array $fallbacks): void
    {
        foreach ($fallbacks as $id => $fallback) {
            // An application is free to bind its own: never overridden.
            if ($container->has($id)) {
                continue;
            }
            foreach ($fallback['candidates'] ?? [] as $candidate) {
                if ($container->has($candidate)) {
                    $container->setAlias($id, $candidate);
                    continue 2;
                }
            }
            $container->register($id, $fallback['default']);
        }
    }

    /** @param array<string, array{prefer: string, over: string}> $preferred */
    private function preferred(ContainerBuilder $container, array $preferred): void
    {
        foreach ($preferred as $alias => $choice) {
            if ($container->has($choice['prefer']) && $container->hasAlias($alias) && $choice['over'] === (string) $container->getAlias($alias)) {
                $container->setAlias($alias, $choice['prefer']);
            }
        }
    }

    /** @param list<array<string, mixed>> $arguments */
    private function arguments(ContainerBuilder $container, array $arguments): void
    {
        foreach ($arguments as $entry) {
            if (!$container->hasDefinition($entry['service'])) {
                continue;
            }
            $source = $entry['symfony'] ?? $entry;
            $value = null;
            if (isset($source['definition'])) {
                [$id, $index] = $source['definition'];
                $found = $container->hasDefinition($id) && \count($container->getDefinition($id)->getArguments()) > $index;
                $value = $found ? $container->getDefinition($id)->getArgument($index) : null;
            } else {
                $found = isset($source['parameter']) && $container->hasParameter($source['parameter']);
                $value = $found ? $container->getParameter($source['parameter']) : null;
                if ($found && !empty($entry['resolve'])) {
                    $value = $container->getParameterBag()->resolveValue($value);
                }
            }
            if (!$found) {
                if (!\array_key_exists('default', $entry)) {
                    continue;
                }
                $value = $entry['default'];
            } else {
                $value = match ($entry['type'] ?? null) {
                    'bool' => (bool) $value,
                    'string' => \is_string($value) ? $value : ($entry['default'] ?? null),
                    default => $value,
                };
            }
            $container->getDefinition($entry['service'])->setArgument($entry['argument'], $value);
        }
    }

    /** @param array{service: string, argument: int|string, when: string, directory: string, reader: callable} $texts */
    private function texts(ContainerBuilder $container, array $texts): void
    {
        if (!$container->hasDefinition($texts['service'])) {
            return;
        }
        $level = $container->hasParameter($texts['when']) ? $container->getParameter($texts['when']) : null;
        $directory = $container->hasParameter($texts['directory']) ? $container->getParameterBag()->resolveValue($container->getParameter($texts['directory'])) : null;
        if (null === $level || '' === $level || !\is_string($directory) || !is_dir($directory)) {
            return;
        }
        // Rebuilt when a catalogue of the application changes (in debug, as the catalogues themselves are).
        $container->addResource(new DirectoryResource($directory, '/\.(ya?ml|php|json)$/'));
        $container->getDefinition($texts['service'])->setArgument($texts['argument'], ($texts['reader'])($directory));
    }

    /** @param array{tag: string, local: array<string, string>} $storages */
    private function storages(ContainerBuilder $container, array $storages): void
    {
        // With league/flysystem-bundle registered, the application's configuration names its storages.
        if ($container->hasExtension(self::FLYSYSTEM_EXTENSION)) {
            return;
        }
        $tagged = [];
        foreach ($container->findTaggedServiceIds($storages['tag']) as $tags) {
            foreach ($tags as $tag) {
                $tagged[$tag['storage'] ?? ''] = true;
            }
        }
        foreach ($storages['local'] as $storage => $directory) {
            if (isset($tagged[$storage]) || $container->has($storage)) {
                continue;
            }
            // As the bundle's local adapter defaults: no lock, links refused, the root created at once.
            $adapter = new Definition(LocalFilesystemAdapter::class, [$directory, null, 0, LocalFilesystemAdapter::DISALLOW_LINKS]);
            $container->setDefinition($storage, (new Definition(Filesystem::class, [$adapter]))->setPublic(false))
                ->addTag($storages['tag'], ['storage' => $storage]);
        }
    }

    /**
     * The enumerations DoctrineBundle's doctrine.dbal.types were given (BaseExtension's prepend, a bundle's) taken out of
     * them: DoctrineBundle instantiates each type's class, and an enumeration is no Doctrine type since step F2a - the
     * connections' type provider holds an adapter of it under the same name (doctrine()).
     *
     * @param array{extends: string} $enumerations
     */
    private function enumerations(ContainerBuilder $container, array $enumerations): void
    {
        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }
        $types = $container->getParameter('doctrine.dbal.connection_factory.types');
        $found = [];
        foreach ($types as $name => $type) {
            $className = $type['class'] ?? null;
            if (\is_string($className) && is_subclass_of($className, $enumerations['extends'])) {
                $found[$name] = $className;
                unset($types[$name]);
            }
        }
        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
        // Read by what builds an entity manager from the container's configuration (a test, a command), and by doctrine().
        $container->setParameter('base.database.enumerations', $found);
    }

    /**
     * omnibridge/doctrine 4.0, on every Doctrine connection and entity manager of the container:
     * - the type provider (Omnibridge\Doctrine\Omnibase\Types::provider(); DBAL's static registry is deprecated in DBAL
     *   4.6, gone in 5): the core's types, DoctrineBundle's doctrine.dbal.types and its doctrine.dbal.type services,
     *   the enumerations given by name (enumerations()), then those of the core's and the application's src/Enum under
     *   the names not taken (EnumerationsPass). It replaces the provider DoctrineBundle 3.4 gives a connection
     *   (TypeProviderGlobalFallback), which would fall back on DBAL's static registry, where omnibase's types no longer are;
     * - doctrine/orm 3.8's schema editor API (DoctrineBundle has no option for it; off is deprecated in ORM 3.8,
     *   impossible in ORM 4), with the foreign keys' names of before kept (ForeignKeyNames, on postGenerateSchema).
     */
    private function doctrine(ContainerBuilder $container): void
    {
        if (!class_exists(Types::class) || !$container->hasParameter('doctrine.connections')) {
            return;
        }
        $types = [];
        foreach ($container->hasParameter('doctrine.dbal.connection_factory.types') ? $container->getParameter('doctrine.dbal.connection_factory.types') : [] as $name => $type) {
            if (\is_string($type['class'] ?? null)) {
                $types[$name] = $container->getParameterBag()->resolveValue($type['class']);
            }
        }
        foreach ($container->findTaggedServiceIds('doctrine.dbal.type') as $id => $tags) {
            foreach ($tags as $tag) {
                $types[$tag['type_name'] ?? $id] = new Reference($id);
            }
        }
        $types += $container->hasParameter('base.database.enumerations') ? $container->getParameter('base.database.enumerations') : [];
        $container->register(self::TYPE_PROVIDER, TypeRegistry::class)
            ->setFactory([Types::class, 'provider'])
            // The list itself, not a reference to its parameter: a container whose parameter was taken out (one compiled
            // before EnumerationsPass, as omnisense/omnibase's StaleContainerTest shapes it) still resolves.
            ->setArguments([$types, $container->hasParameter(EnumerationsPass::PARAMETER) ? $container->getParameter(EnumerationsPass::PARAMETER) : []]);
        foreach (array_keys($container->getParameter('doctrine.connections')) as $connection) {
            $configuration = \sprintf('doctrine.dbal.%s_connection.configuration', $connection);
            if ($container->hasDefinition($configuration)) {
                $container->getDefinition($configuration)->removeMethodCall('setTypeProvider')->addMethodCall('setTypeProvider', [new Reference(self::TYPE_PROVIDER)]);
            }
        }

        if (!$container->hasParameter('doctrine.entity_managers') || !class_exists(\Omnibridge\Doctrine\ForeignKeyNames::class)) {
            return;
        }
        // Added to each connection's event manager itself: DoctrineBundle's listener tags were read already.
        $container->register(\Omnibridge\Doctrine\ForeignKeyNames::class, \Omnibridge\Doctrine\ForeignKeyNames::class);
        foreach (array_keys($container->getParameter('doctrine.connections')) as $connection) {
            $events = \sprintf('doctrine.dbal.%s_connection.event_manager', $connection);
            if ($container->hasDefinition($events)) {
                $container->getDefinition($events)->addMethodCall('addEventListener', [[\Doctrine\ORM\Tools\ToolEvents::postGenerateSchema], new Reference(\Omnibridge\Doctrine\ForeignKeyNames::class)]);
            }
        }
        foreach (array_keys($container->getParameter('doctrine.entity_managers')) as $entityManager) {
            $configuration = \sprintf('doctrine.orm.%s_configuration', $entityManager);
            if ($container->hasDefinition($configuration)) {
                $container->getDefinition($configuration)->addMethodCall('setUseSchemaEditorApi', [true]);
            }
        }
    }

    /**
     * The registered omnibase bundles as Base\Discovery's packages: each bundle whose class extends AbstractBaseBundle
     * (BaseBundle left out: the core is a package of its own), by its package's folder (the one above its src/) => its
     * namespace, in the kernel's order.
     *
     * @return array<string, string>
     */
    public static function packages(ContainerBuilder $container): array
    {
        $packages = [];
        $metadata = $container->hasParameter('kernel.bundles_metadata') ? $container->getParameter('kernel.bundles_metadata') : [];
        foreach ($container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [] as $name => $class) {
            if (!\is_string($class) || \Base\BaseBundle::class === $class || !is_subclass_of($class, \Base\Bundle\AbstractBaseBundle::class)) {
                continue;
            }
            // Bundle::getPath(): the bundle class's folder (its package's src/), or the package's own where the bundle
            // says so (Symfony's AbstractBundle, a bundle overriding it).
            $path = $metadata[$name]['path'] ?? null;
            if (!\is_string($path)) {
                continue;
            }
            $path = rtrim($path, '/');
            $root = 'src' === basename($path) ? \dirname($path) : $path;
            if (is_dir($root.'/src')) {
                $packages[$root] = rtrim((string) ($metadata[$name]['namespace'] ?? substr($class, 0, (int) strrpos($class, '\\'))), '\\');
            }
        }

        return $packages;
    }

    /**
     * The entities' folders (Base\Discovery::entities()) given to the parameter ENTITIES and, where DoctrineBundle is
     * registered, to its default entity manager's mapping.
     *
     * @param array{directories: list<string>, requires: string} $entities
     */
    private function entities(ContainerBuilder $container, array $entities): void
    {
        $projectDir = (string) $container->getParameter('kernel.project_dir');
        $packages = self::packages($container);
        $resolve = static fn (mixed $value): mixed => $container->getParameterBag()->resolveValue($value);
        $include = [];
        foreach ((array) $resolve($container->hasParameter('base.entity_folders.include') ? $container->getParameter('base.entity_folders.include') : []) as $folder => $namespace) {
            $include[(string) $folder] = \is_string($namespace) && '' !== $namespace ? $namespace : null;
        }
        $exclude = array_values(array_map('strval', (array) $resolve($container->hasParameter('base.entity_folders.exclude') ? $container->getParameter('base.entity_folders.exclude') : [])));
        $paths = \Base\Discovery::entities($projectDir, $packages, $include);
        foreach (array_keys($paths) as $path) {
            $container->fileExists($path, false);
        }
        $targets = \Base\Discovery::targets($packages);
        $container->setParameter(self::ENTITIES, ['paths' => $paths, 'exclude' => $exclude, 'packages' => $packages, 'resolve_target_entities' => $targets]);

        if (!$container->hasParameter('doctrine.default_entity_manager') || !class_exists(\Doctrine\ORM\Mapping\Driver\AttributeDriver::class)) {
            return;
        }
        $entityManager = (string) $container->getParameter('doctrine.default_entity_manager');
        $chain = \sprintf('doctrine.orm.%s_metadata_driver', $entityManager);
        if (!$container->hasDefinition($chain)) {
            return;
        }

        // What DoctrineBundle maps already (auto_mapping, the mappings a package or doctrine.yaml declares): kept.
        $mapped = [];
        $prefixes = [];
        $drivers = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (preg_match('/^doctrine\.orm\.[^.]+_attribute_metadata_driver$/', $id)) {
                $drivers[] = $definition;
                foreach ((array) ($definition->getArguments()[0] ?? []) as $path) {
                    if (\is_string($path)) {
                        $mapped[realpath($resolve($path)) ?: rtrim($resolve($path), '/')] = true;
                    }
                }
            }
        }
        $chainDefinition = $container->getDefinition($chain);
        foreach ($chainDefinition->getMethodCalls() as [$method, $arguments]) {
            if ('addDriver' === $method && isset($arguments[1]) && \is_string($arguments[1])) {
                $prefixes[$arguments[1]] = true;
            }
        }
        $discovered = [];
        foreach ($paths as $path => $namespace) {
            if (!isset($mapped[realpath($path) ?: $path]) && !isset($prefixes[$namespace])) {
                $discovered[$namespace] = $path;
            }
        }
        if ([] !== $discovered) {
            $driver = new Definition(\Doctrine\ORM\Mapping\Driver\AttributeDriver::class, [array_values($discovered)]);
            $container->setDefinition(self::DISCOVERED_DRIVER, $driver);
            $drivers[] = $driver;
            foreach (array_keys($discovered) as $namespace) {
                $chainDefinition->addMethodCall('addDriver', [new Reference(self::DISCOVERED_DRIVER), $namespace]);
            }
        }
        if ([] !== $exclude) {
            foreach ($drivers as $driver) {
                $driver->addMethodCall('addExcludePaths', [$exclude]);
            }
        }

        if ([] === $targets || !$container->hasDefinition('doctrine.orm.listeners.resolve_target_entity')) {
            return;
        }
        $listener = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');
        foreach ($listener->getMethodCalls() as [$method, $arguments]) {
            if ('addResolveTargetEntity' === $method) {
                unset($targets[$arguments[0] ?? '']);
            }
        }
        if ([] === $targets) {
            return;
        }
        foreach ($targets as $interface => $class) {
            $listener->addMethodCall('addResolveTargetEntity', [$interface, $class, []]);
        }
        $events = array_column($listener->getTag('doctrine.event_listener'), 'event');
        foreach (['loadClassMetadata', 'onClassMetadataNotFound'] as $event) {
            if (!\in_array($event, $events, true)) {
                $listener->addTag('doctrine.event_listener', ['event' => $event]);
            }
        }
    }

    /**
     * An entity's repositoryClass may name one of the core's repository interfaces (step F3): DoctrineBundle's
     * repository factory then hands out the repository the interface is bound to - each interface added to its locator
     * under its name, as that repository.
     *
     * @param array<string, string> $repositories interface => repository
     */
    private function repositories(ContainerBuilder $container, array $repositories): void
    {
        if (!$container->hasDefinition('doctrine.orm.container_repository_factory')) {
            return;
        }
        $locator = $container->getDefinition('doctrine.orm.container_repository_factory')->getArgument(0);
        if (!$locator instanceof Reference || !$container->hasDefinition((string) $locator)) {
            return;
        }
        $definition = $container->getDefinition((string) $locator);
        $services = $definition->getArgument(0);
        if (!\is_array($services)) {
            return;
        }
        foreach ($repositories as $interface => $repository) {
            if (isset($services[$repository]) && !isset($services[$interface])) {
                $services[$interface] = $services[$repository];
            }
        }
        $definition->replaceArgument(0, $services);
    }
}
