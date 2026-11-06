<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Base\Routing\AdminUrlGeneratorInterface;
use Base\Routing\NullAdminUrlGenerator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Binds Base\Routing\AdminUrlGeneratorInterface to whatever back-office is
 * installed.
 *
 * base-bundle ships form types and Twig helpers that link back to the admin,
 * but a front-end-only install has no admin at all. Resolving the binding in
 * a compiler pass - rather than by registering competing service ids in two
 * bundles - makes the outcome independent of bundle load order.
 */
final class AdminUrlGeneratorPass implements CompilerPassInterface
{
    private const ADMIN_GENERATOR = 'Base\Admin\Router\AdminUrlGenerator';

    public function process(ContainerBuilder $container): void
    {
        // an application is free to bind its own generator; never override it
        if ($container->has(AdminUrlGeneratorInterface::class)) {
            return;
        }

        if ($container->has(self::ADMIN_GENERATOR)) {
            $container->setAlias(AdminUrlGeneratorInterface::class, self::ADMIN_GENERATOR);

            return;
        }

        $container->register(AdminUrlGeneratorInterface::class, NullAdminUrlGenerator::class);
    }
}
