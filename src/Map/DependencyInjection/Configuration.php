<?php

namespace Omnibridge\Symfony\Map\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * config/packages/omnimap.yaml:
 *
 *     omnimap:
 *         provider: openstreetmap            # the provider of the page's map: a key of providers
 *         secret: '%env(MAP_SECRET)%'        # signs the pictures' names (URL-safe base64)
 *         providers:
 *             google: { api_key: '...', server_key: '...', secret: '...', legacy_signature: true }
 *             openstreetmap: { user_agent: 'MySite/1.0 (+https://example.org)' }
 *         cache:
 *             storage: App\Map\Storage       # a service implementing Omnimap\Snapshot\Storage
 *         routes:
 *             tile: { path: '/omnimap/{signature}/{id}.webp' }
 *
 * Every provider's options are its factory's (omnimap/google, omnimap/openstreetmap).
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('omnimap');
        $route = static fn (string $name, string $path) => (new TreeBuilder($name))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('path')->defaultValue($path)->end()
                ->scalarNode('name')->defaultValue('omnimap_'.$name)->end()
            ->end();

        $tree->getRootNode()
            ->children()
                ->scalarNode('provider')->isRequired()->cannotBeEmpty()->info('The provider of the page\'s map: a key of "providers".')->end()
                ->scalarNode('geocoder')->defaultNull()->info('The provider that geocodes (Omnimap\GeocoderInterface): a key of "providers"; the page\'s provider by default.')->end()
                ->scalarNode('secret')->defaultValue('')->info('Signs the pictures\' names (URL-safe base64; Google\'s URL signing secret fits).')->end()
                ->arrayNode('providers')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                ->end()
                ->booleanNode('cache_only')->defaultTrue()->info('A visitor never gets the live map: the picture kept, or an empty frame.')->end()
                ->scalarNode('control')->defaultValue('ROLE_ADMIN')->info('Who may capture and delete pictures, and sees the live map while there is none.')->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage')->isRequired()->cannotBeEmpty()->info('A service implementing Omnimap\Snapshot\Storage.')->end()
                        ->scalarNode('directory')->defaultValue('omnimap')->end()
                        ->scalarNode('pattern')->defaultValue('{signature}/image-{id}')->end()
                        ->enumNode('format')->values(['png', 'jpeg', 'jpg', 'webp'])->defaultValue('png')->end()
                        ->integerNode('tilesize')->defaultValue(512)->end()
                        ->scalarNode('make')->defaultValue('auto')->info('Whether the server makes a missing picture itself: auto (when the provider makes images), true, false.')->end()
                        ->scalarNode('size')->defaultValue('1512x938')->end()
                        ->integerNode('scale')->defaultValue(1)->min(1)->max(2)->end()
                        ->scalarNode('pool')->defaultValue('cache.app')->info('The PSR-6 pool keeping fetched tiles and geocoders\' answers.')->end()
                        ->integerNode('max_age')->defaultValue(86400)->info('The HTTP lifetime of a tile served by the route.')->end()
                        ->scalarNode('public_directory')->defaultNull()->info('Where a tile served is also written (the web server then serves it without PHP): the public folder.')->end()
                        ->integerNode('public_size')->defaultNull()->info('The largest side of a tile served, in pixels (none: as kept).')->end()
                    ->end()
                ->end()
                ->arrayNode('html')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')->defaultValue('omnimap-snapshot')->end()
                        ->scalarNode('suffix')->defaultValue('_snapshot')->end()
                        ->scalarNode('missing')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($route('tile', '/omnimap/{signature}/{id}.webp'))
                        ->append($route('metadata', '/omnimap/{signature}'))
                        ->append($route('export', '/omnimap/{signature}/export'))
                        ->append($route('suppress', '/omnimap/{signature}/suppress'))
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
