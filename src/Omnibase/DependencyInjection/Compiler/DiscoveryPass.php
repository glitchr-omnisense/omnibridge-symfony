<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler;

use Omnibridge\Doctrine\Omnibase\EnumerationsMiddleware;
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
 *   DoctrineBundle's repository locator - after its repository pass (RepositoryInterfaces).
 *
 * `autoconfigure` is compiled by BaseExtension::load() (autoconfigure()): Symfony reads it before any compiler pass.
 * What is Symfony's own knowledge stays here: when each kind runs (PRIORITIES), DoctrineBundle's and the flysystem
 * bundle's names.
 */
final class DiscoveryPass implements CompilerPassInterface
{
    /**
     * The kinds compiled at each moment of Symfony's compilation: the enumerations before DoctrineBundle's
     * MiddlewaresPass (priority 0) collects the doctrine.middleware services, the repositories after its
     * ServiceRepositoryCompilerPass made the locator, the others where the former passes ran.
     */
    public const PRIORITIES = [
        10 => ['enumerations'],
        0 => ['collect', 'contracts', 'fallbacks', 'preferred', 'arguments', 'texts', 'storages'],
        -10 => ['repositories'],
    ];

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
            foreach (array_keys($container->getDefinitions()) as $id) {
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
     * them: DoctrineBundle's connection factory instantiates each type's class, and an enumeration is no Doctrine type
     * since step F2a - omnibridge/doctrine's middleware registers an adapter of it under the same name.
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
        // Read by what builds an entity manager from the container's configuration (a test, a command).
        $container->setParameter('base.database.enumerations', $found);
        $container->register(EnumerationsMiddleware::class, EnumerationsMiddleware::class)
            ->setArguments([$found])
            ->addTag('doctrine.middleware');
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
