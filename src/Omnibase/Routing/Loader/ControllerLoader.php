<?php

namespace Omnibridge\Symfony\Omnibase\Routing\Loader;

use Omnibridge\Symfony\Omnibase\BaseBundle;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of omnibase's own controllers, which an application imports once:
 *
 *     base_controller:
 *         resource: .
 *         type: omnibase
 *         prefix: /
 *
 * Since 5.0 (plan "omnibase agnostic", step F6) they live in two places: the Symfony
 * controllers here, in omnibridge/symfony (Omnibase\Controller), and the actions written
 * once for every host in the core (Base\Controller\Action, and SignatureController), which
 * omnibridge/laravel reads as well. Both folders are imported as attribute routes, the
 * core's after this package's; each folder is a resource of the route cache, as the
 * former `@BaseBundle/Controller` import was.
 */
class ControllerLoader extends Loader
{
    public const TYPE = 'omnibase';

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        foreach (self::directories() as $directory) {
            $routes->addCollection($this->import($directory, 'attribute'));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    /**
     * The folders read, this package's then the core's.
     *
     * @return list<string>
     */
    public static function directories(): array
    {
        return [
            \dirname(__DIR__, 2).'/Controller',
            BaseBundle::getBundleDir().'/src/Controller',
        ];
    }
}
