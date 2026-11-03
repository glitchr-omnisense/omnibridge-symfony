<?php

namespace Omnibridge\Symfony\Omnibase\Routing\Loader;

use Omnibridge\Symfony\Omnibase\BaseBundle;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\DirectoryResource;
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
 * omnibridge/laravel reads as well. The files of both folders are imported as attribute
 * routes in the order the former `@BaseBundle/Controller` import read them (files()); each
 * folder is a resource of the route cache, as that import's was.
 */
class ControllerLoader extends Loader
{
    public const TYPE = 'omnibase';

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        foreach (self::files() as $file) {
            $routes->addCollection($this->import($file, 'attribute'));
        }
        foreach (self::directories() as $directory) {
            $routes->addResource(new DirectoryResource($directory, '/\.php$/'));
        }

        return $routes;
    }

    /**
     * The controllers' files of both folders, in the order the former `@BaseBundle/Controller` import read them
     * from the one folder they shared until 5.0 - Symfony's attribute directory loader sorts by path -, so that
     * the routes come in the same order (the first route that matches a path wins).
     *
     * @return list<string>
     */
    public static function files(): array
    {
        $files = [];
        foreach (self::directories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $found = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS));
            foreach ($found as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[substr($file->getPathname(), \strlen($directory))] = $file->getPathname();
                }
            }
        }
        uksort($files, static fn (string $a, string $b): int => $a > $b ? 1 : -1);

        return array_values($files);
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
