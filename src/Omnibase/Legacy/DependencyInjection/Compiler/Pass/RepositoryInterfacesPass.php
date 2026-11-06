<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Base\Repository\Aliases;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * An entity's repositoryClass may name one of the core's repository interfaces
 * (Base\Repository\Thread\Tags...) instead of a class (plan "omnibase agnostic", step
 * F3): DoctrineBundle's repository factory then hands out the repository the
 * container binds to it (Base\Repository\Aliases::INTERFACES), as omnibridge/doctrine's
 * RepositoryFactory does for an entity manager built by hand. DoctrineBundle's
 * locator holds the services tagged doctrine.repository_service by their ids; each
 * interface is added to it, under its name, as the repository it is bound to.
 *
 * After DoctrineBundle's ServiceRepositoryCompilerPass, which makes that locator.
 */
class RepositoryInterfacesPass implements CompilerPassInterface
{
    public static function getPriority(): int
    {
        return -10;
    }

    public function process(ContainerBuilder $container): void
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

        foreach (Aliases::INTERFACES as $interface => $repository) {
            if (isset($services[$repository]) && !isset($services[$interface])) {
                $services[$interface] = $services[$repository];
            }
        }

        $definition->replaceArgument(0, $services);
    }
}
