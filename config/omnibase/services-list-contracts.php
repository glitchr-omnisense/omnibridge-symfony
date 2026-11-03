<?php

use Base\Services;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The contracts of the core's list of services (Base\Services::definitions(), plan "omnibase agnostic", step F5a):
 * each one the configuration does not alias itself is aliased to its service. Loaded last (BaseExtension), so
 * that the aliases of config/services*.php stay what they are; the services themselves are defined first, from
 * the list (services-list.php).
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();
    foreach (Services::definitions() as $definition) {
        $id = $definition['id'] ?? $definition['class'];
        if (!$builder->has($id)) {
            continue;
        }
        foreach ($definition['contracts'] ?? [] as $contract) {
            if (!$builder->has($contract)) {
                $services->alias($contract, $id);
            }
        }
    }
};
