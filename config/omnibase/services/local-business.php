<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * schema.org's LocalBusiness (docs/40-commons/local-business.md): the
 * settings, base.local_business and the opening hours, as the page's JSON-LD.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()
        ->public(false)
        ->autowire()
        ->autoconfigure();

    $services->set('Base\Service\LocalBusiness')->public()
        ->arg('$settings', service('setting_bag')->nullOnInvalid())
        ->arg('$requests', service('request_stack')->nullOnInvalid());

    // local_business() and local_business_jsonld() in a template: with the Twig layer (config/services/twig.php).
};
