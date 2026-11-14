<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Omnibridge\Doctrine\Omnibase\EnumerationsMiddleware;
use Base\Database\Type\EnumType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The enumerations given to DoctrineBundle's doctrine.dbal.types (the core's and the application's
 * by BaseExtension, a bundle's by its own prepend) taken out of them and given to
 * Omnibridge\Doctrine\Omnibase\EnumerationsMiddleware (plan "omnibase agnostic", step F2a): DoctrineBundle's
 * connection factory instantiates each type's class, and an enumeration is no Doctrine type any more -
 * the middleware registers an EnumTypeAdapter of it under the same name, when the connection is made.
 */
class EnumerationTypesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }

        $types = $container->getParameter('doctrine.dbal.connection_factory.types');
        $enumerations = [];
        foreach ($types as $name => $type) {
            $className = $type['class'] ?? null;
            if (\is_string($className) && is_subclass_of($className, EnumType::class)) {
                $enumerations[$name] = $className;
                unset($types[$name]);
            }
        }
        $container->setParameter('doctrine.dbal.connection_factory.types', $types);
        // Read by what builds an entity manager from the container's configuration (a test, a command).
        $container->setParameter('base.database.enumerations', $enumerations);

        $container->register(EnumerationsMiddleware::class, EnumerationsMiddleware::class)
            ->setArguments([$enumerations])
            ->addTag('doctrine.middleware');
    }

    /** Before DoctrineBundle's middleware pass (MiddlewaresPass, priority 0) collects the doctrine.middleware services. */
    public static function getPriority(): int
    {
        return 10;
    }
}
