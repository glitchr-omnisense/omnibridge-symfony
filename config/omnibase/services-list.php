<?php

use Base\Services;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The core's list of services (Base\Services::definitions(), plan "omnibase agnostic", steps F5a and F5c item 3),
 * read by Symfony's container as Laravel's and Omnibridge\Container read it: each listed service is defined from
 * the list - its class, its id, autowired, public, its arguments given ('@id' a service, '%name%' a parameter, as
 * Symfony writes them). Loaded first (BaseExtension): config/services/*.php and services-decoration.php then say
 * only what Symfony adds to a listed service, through $services->get() - its tags, its decoration, the class of
 * its Symfony half (BaseService's), the framework's services it is given in place of what the list autowires.
 * The contracts the list names are aliased last (services-list-contracts.php). tests/ServicesTest checks that
 * Symfony's container answers the list.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();
    $reference = static function (mixed $value) use (&$reference): mixed {
        if (\is_array($value)) {
            return array_map($reference, $value);
        }

        return \is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@@') ? service(substr($value, 1)) : (\is_string($value) && str_starts_with($value, '@@') ? substr($value, 1) : $value);
    };
    foreach (Services::definitions() as $definition) {
        $id = $definition['id'] ?? $definition['class'];
        if ($builder->has($id) || !class_exists($definition['class'])) {
            continue;
        }
        $arguments = [];
        foreach ($definition['arguments'] ?? [] as $name => $value) {
            $arguments[str_starts_with($name, '$') ? $name : '$'.$name] = $reference($value);
        }
        $services->set($id, $definition['class'])->autowire()->public()->args($arguments);
    }
};
