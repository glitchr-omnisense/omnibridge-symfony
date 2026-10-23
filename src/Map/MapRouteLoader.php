<?php

namespace Omnibridge\Symfony\Map;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The map cache's routes, at the paths and under the names omnimap.yaml
 * gives (routes): a tile (GET), a picture's metadata (GET), the export of a
 * captured picture and its deletion (POST). An application imports them:
 *
 *     omnimap: { resource: ., type: omnimap }
 */
final class MapRouteLoader extends Loader
{
    public const TYPE = 'omnimap';

    /** @param array<string, array{path: string, name: string}> $routes */
    public function __construct(private readonly array $routes, ?string $env = null)
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();
        $signature = ['signature' => '[A-Za-z0-9_-]{1,128}'];
        $controller = SnapshotController::class;
        // The tile first: its path may lie under the metadata's, and routes match in order.
        $collection->add($this->routes['tile']['name'], new Route($this->routes['tile']['path'], ['_controller' => $controller.'::tile', 'id' => 0], $signature + ['id' => '\d+'], methods: ['GET', 'HEAD']));
        $collection->add($this->routes['export']['name'], new Route($this->routes['export']['path'], ['_controller' => $controller.'::export'], $signature, methods: ['POST']));
        $collection->add($this->routes['suppress']['name'], new Route($this->routes['suppress']['path'], ['_controller' => $controller.'::suppress'], $signature, methods: ['POST']));
        $collection->add($this->routes['metadata']['name'], new Route($this->routes['metadata']['path'], ['_controller' => $controller.'::metadata'], $signature, methods: ['GET', 'HEAD']));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
