<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Symfony\Omnibase\Routing\Generator\AdvancedUrlGenerator;
use Omnibridge\Symfony\Omnibase\Routing\Matcher\AdvancedUrlMatcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Symfony's router matches and generates through omnibase's matcher and generator: its options name them when the
 * container is compiled (matcher_class, generator_class of router.default). AdvancedRouter's constructor set them, so
 * every kernel's boot made it - with Symfony's router, the localizer, the firewall map and the cache it is given - for
 * a command or a request that matches and generates nothing. The matcher and the generator make it now when they are
 * first made (AdvancedRouter::current(), from the container BaseBundle::boot() hands).
 */
final class AdvancedRouterPass implements CompilerPassInterface
{
    /**
     * True where this pass named omnibase's matcher and generator on Symfony's router. A container compiled before it
     * has no such parameter, and BaseBundle::boot() makes the router there as before, its constructor naming both.
     */
    public const PARAMETER = 'omnibridge.router.advanced';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('advanced_router') || !$container->hasDefinition('router.default')) {
            return;
        }

        $router = $container->getDefinition('router.default');
        $options = $router->getArgument(2);
        if (!\is_array($options)) {
            return;
        }

        $options['matcher_class'] = AdvancedUrlMatcher::class;
        $options['generator_class'] = AdvancedUrlGenerator::class;
        $router->replaceArgument(2, $options);
        $container->setParameter(self::PARAMETER, true);
    }
}
