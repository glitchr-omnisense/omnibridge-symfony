<?php

namespace Base\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RouteCollection;

/**
 * Registers config/routes_paths.yaml as a tracked resource so Symfony's
 * config-cache rebuilds routes when it changes. Base\Attributes\Attribute\Route
 * reads that file directly (not through Symfony's routing loaders), so
 * without this, editing it alone — without touching any controller file —
 * would leave the compiled route cache stale: AttributeClassLoader's
 * freshness tracking is per-controller-class, not aware of this file at all.
 *
 * Registers no routes itself; loaded purely for its addResource() side effect.
 * Extends FileLoader (not the bare Loader) specifically to reuse its
 * FileLocator for resolving the resource path the same way YamlFileLoader
 * and friends do (relative to the importing routes.yaml's directory).
 */
class RouteTranslationsResourceLoader extends FileLoader
{
    public const TYPE = "base_route_paths";

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $path = $this->locator->locate($resource);

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($path));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
