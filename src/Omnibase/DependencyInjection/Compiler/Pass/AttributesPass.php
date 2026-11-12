<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Base\Attributes\AttributeReader;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The attributes omnisense/omnibase's AttributeReader knows, found once when the container is compiled: the classes of
 * the folders it searches (the core's, those of base.attributes.paths) that are attributes, in the parameter
 * AttributeReader::NAMES. The reader's constructor read the folders and loaded each of their classes for every kernel
 * that made it; it reads the list now. Each folder is a resource of the container: in debug, a file added, changed or
 * removed there rebuilds the container, and the list with it (a folder that does not exist yet as well, once made).
 *
 * Where the core has no such list (AttributeReader::discover(), omnisense/omnibase 5.x before it), nothing is done:
 * its reader reads the folders itself, as before.
 */
final class AttributesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(AttributeReader::class) || !\defined(AttributeReader::class.'::NAMES') || !$container->has(AttributeReader::class)) {
            return;
        }

        // base.attributes.paths whole and item by item (omnibase's flattened parameters), resolved as the reader's
        // container gives them (%kernel.project_dir%/src/Attribute).
        $bag = $container->getParameterBag();
        $parameters = array_filter($bag->all(), static fn ($name): bool => str_starts_with((string) $name, 'base.attributes.paths'), \ARRAY_FILTER_USE_KEY);
        $paths = AttributeReader::searchedPaths($bag->resolveValue($parameters));

        foreach ($paths as $path) {
            $container->addResource(match (true) {
                is_dir($path) => new DirectoryResource($path, '/\.php$/'),
                is_file($path) => new FileResource($path),
                default => new FileExistenceResource($path),
            });
        }

        $container->setParameter(AttributeReader::NAMES, AttributeReader::discover($paths));
    }
}
