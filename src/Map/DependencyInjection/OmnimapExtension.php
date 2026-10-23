<?php

namespace Omnibridge\Symfony\Map\DependencyInjection;

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Symfony\Map\MapRouteLoader;
use Omnibridge\Symfony\Map\SnapshotController;
use Omnibridge\Twig\MapExtension;
use Omnimap\GeocoderInterface;
use Omnimap\Google\GoogleFactory;
use Omnimap\MapHtml;
use Omnimap\MapPrinter;
use Omnimap\MapRendererInterface;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;
use Omnimap\Signer;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\StaticMapInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * omniscient/omnimap wired into a Symfony application, from
 * config/packages/omnimap.yaml (Configuration): the providers installed
 * (omnimap/google, omnimap/openstreetmap) over the application's HTTP
 * client, a PSR-16 view of its cache pool and, for geocoder-php, a PSR-18
 * client; the Registry; the map cache (SnapshotStore over the storage
 * service the application names, Snapshots, the server's own pictures when
 * the provider makes images); MapPrinter and omnibridge/twig's MapExtension
 * (omnimap(), omnimap_export(), omnimap_suppress()); the cache's routes
 * (type "omnimap") and their controller.
 *
 * Services by their class: Omnimap\Registry, Omnimap\Snapshot\Snapshots,
 * Omnimap\MapPrinter, Omnimap\MapRendererInterface (the page's provider),
 * Omnimap\GeocoderInterface (the provider "geocoder" names, the page's by default).
 *
 * Registered by OmnibridgeBundle where omnimap is installed; with no
 * omnimap.yaml, it loads nothing.
 */
final class OmnimapExtension extends Extension
{
    public const FACTORY = 'omnimap.provider_factory';

    /** Registers this extension in a container being built, when omnimap is installed. */
    public static function register(ContainerBuilder $container): void
    {
        if (class_exists(Registry::class) && !$container->hasExtension('omnimap')) {
            $container->registerExtension(new self());
        }
    }

    public function getAlias(): string
    {
        return 'omnimap';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        if ([] === array_filter($configs)) {
            return;
        }
        /** @var array{provider: string, geocoder: ?string, secret: string, providers: array<string, array<string, mixed>|null>, cache_only: bool, control: string, cache: array{storage: string, directory: string, pattern: string, format: string, tilesize: int, make: string|bool, size: string, scale: int, pool: string, max_age: int, public_directory: ?string, public_size: ?int}, html: array<string, string>, routes: array<string, array{path: string, name: string}>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->register('omnimap.cache', Psr16Cache::class)->setArguments([new Reference($config['cache']['pool'])]);
        $psr18 = null;
        if (class_exists(Psr18Client::class) && (class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class) || class_exists(\Http\Discovery\Psr17FactoryDiscovery::class))) {
            $container->register('omnimap.psr18', Psr18Client::class)->setArguments([new Reference('http_client')]);
            $psr18 = new Reference('omnimap.psr18');
        }
        $http = new Reference('http_client');
        foreach ([GoogleFactory::class, OpenStreetMapFactory::class] as $factory) {
            if (class_exists($factory)) {
                $container->register($factory, $factory)->setArguments([$http, $psr18, new Reference('omnimap.cache')])->addTag(self::FACTORY);
            }
        }

        $providers = [];
        foreach ($config['providers'] as $name => $options) {
            $providers[$name] = ['factory' => \is_string($options['factory'] ?? null) ? $options['factory'] : $name, 'options' => array_diff_key($options ?? [], ['factory' => true])];
        }
        $container->register(Registry::class, Registry::class)
            ->setArguments([new \Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument(self::FACTORY), $providers])
            ->setPublic(true);

        $provider = $config['provider'];
        $container->register(MapRendererInterface::class, MapRendererInterface::class)
            ->setFactory([new Reference(Registry::class), 'renderer'])->setArguments([$provider]);

        $container->register(GeocoderInterface::class, GeocoderInterface::class)
            ->setFactory([new Reference(Registry::class), 'geocoder'])->setArguments([$config['geocoder'] ?? $provider]);

        $cache = $config['cache'];
        $make = 'auto' === $cache['make'] ? null : filter_var($cache['make'], \FILTER_VALIDATE_BOOL);
        $static = null;
        if (false !== $make) {
            $static = (new Definition(StaticMapInterface::class))->setFactory([self::class, 'staticMap'])->setArguments([new Reference(Registry::class), $provider, true === $make]);
        }
        $container->register(SnapshotStore::class, SnapshotStore::class)
            ->setArguments([new Reference($cache['storage']), $cache['directory'], $cache['pattern'], $cache['format']]);
        $container->register(Snapshots::class, Snapshots::class)
            ->setArguments([
                new Reference(MapRendererInterface::class),
                new Definition(Signer::class, [$config['secret']]),
                new Reference(SnapshotStore::class),
                $static,
                (new Definition(Size::class))->setFactory([Size::class, 'fromString'])->setArguments([$cache['size']]),
                $cache['scale'],
                $cache['tilesize'] > 0 ? $cache['tilesize'] : null,
            ])
            ->setPublic(true);

        $html = $config['html'] + ['src' => $config['routes']['tile']['path']];
        $container->register(MapHtml::class, MapHtml::class)->setArguments([$html]);
        $container->register('omnimap.error_logger', \Closure::class)
            ->setFactory([self::class, 'logger'])
            ->setArguments([new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(MapPrinter::class, MapPrinter::class)
            ->setArguments([new Reference(Snapshots::class), new Reference(MapHtml::class), $config['cache_only'], new Reference('omnimap.error_logger')])
            ->setPublic(true);

        $routes = array_map(static fn (array $route) => $route['name'], $config['routes']);
        if (class_exists(MapExtension::class) && $container->hasParameter('kernel.bundles') && \is_array($bundles = $container->getParameter('kernel.bundles')) && isset($bundles['TwigBundle'])) {
            $container->register(MapExtension::class, MapExtension::class)
                ->setArguments([new Reference(MapPrinter::class), new Reference(Urls::class), new Reference(Access::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(Tokens::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['control'], $routes])
                ->addTag('twig.extension');
        }

        $container->register(SnapshotController::class, SnapshotController::class)
            ->setArguments([
                new Reference(Snapshots::class),
                new Reference(Access::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(Tokens::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['control'],
                $config['routes']['tile']['path'],
                $cache['public_directory'],
                $cache['public_size'],
                $cache['max_age'],
                $cache['tilesize'] > 0 ? $cache['tilesize'] : null,
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(MapRouteLoader::class, MapRouteLoader::class)
            ->setArguments([$config['routes']])
            ->addTag('routing.loader');
    }

    /** The page's provider as a static map: required (make: true), or when it has the capability (auto). */
    public static function staticMap(Registry $registry, string $provider, bool $required): ?StaticMapInterface
    {
        $map = $registry->get($provider);
        if ($map instanceof StaticMapInterface && ($required || $map->capabilities()->staticMap)) {
            return $map;
        }

        return $required ? $registry->staticMap($provider) : null;
    }

    /** @return \Closure(\Throwable): void */
    public static function logger(?LoggerInterface $logger): \Closure
    {
        return static function (\Throwable $e) use ($logger): void {
            $logger?->warning('omnimap: the map\'s picture could not be made: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        };
    }
}
