<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Doctrine\Omnibase\Configuration;
use Omnibridge\Doctrine\Omnibase\MappingDriver;
use Omnibridge\Doctrine\DeclarationDriver;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * omnibase's Doctrine mapping on DoctrineBundle's entity managers, from the
 * same lists and the same driver an entity manager built by hand gets from
 * Base\Bridge\Doctrine\Mapping (plan "omnibase agnostique", C6):
 *
 * - the filters, the DQL functions and the default hint of
 *   Base\Bridge\Doctrine\Configuration, on `doctrine.orm.configuration`;
 * - every entity manager's mapping driver wrapped in omnibridge/doctrine's
 *   DeclarationDriver (the metadata built from the classes' declarations,
 *   Omnibridge\Schema\Declaration: the structure described outside Doctrine,
 *   audit R14), then in Base\Bridge\Doctrine\MappingDriver (the discriminator
 *   maps generated, the `resolveDiscriminator` event), given its entity
 *   manager lazily.
 */
class DoctrineConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.orm.configuration')) {
            return;
        }

        $definition = $container->getDefinition('doctrine.orm.configuration');

        foreach (Configuration::filters() as $name => $className) {
            $definition->addMethodCall('addFilter', [$name, $className]);
        }
        foreach (Configuration::numericFunctions() as $name => $className) {
            $definition->addMethodCall('addCustomNumericFunction', [$name, $className]);
        }
        foreach (Configuration::stringFunctions() as $name => $className) {
            $definition->addMethodCall('addCustomStringFunction', [$name, $className]);
        }
        foreach (Configuration::defaultQueryHints() as $name => $value) {
            $definition->addMethodCall('setDefaultQueryHint', [$name, $value]);
        }

        $entityManagers = $container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [];
        foreach ($entityManagers as $name => $entityManager) {
            $id = sprintf('doctrine.orm.%s_configuration', $name);
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $configuration = $container->getDefinition($id);
            $calls = $configuration->getMethodCalls();
            foreach ($calls as $i => [$method, $arguments]) {
                if ($method !== 'setMetadataDriverImpl' || !($arguments[0] ?? null) instanceof Reference) {
                    continue;
                }

                // Doctrine reads the classes' declarations (omnibridge/doctrine's DeclarationDriver over the
                // application's driver, audit R14), and around it omnibase's MappingDriver.
                $driver = new Definition(MappingDriver::class, [new Definition(DeclarationDriver::class, [$arguments[0]]), new ServiceClosureArgument(new Reference($entityManager))]);
                $calls[$i] = [$method, [$driver]];
            }
            $configuration->setMethodCalls($calls);
        }
    }
}
