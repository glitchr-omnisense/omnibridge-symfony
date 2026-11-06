<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * schema.org's LocalBusiness (docs/40-commons/local-business.md): the settings, base.local_business and the opening
 * hours, as the page's JSON-LD. The core's declaration (Base\Services, group "local-business"; plan "omnibase
 * agnostic", step F5e slice 4); local_business() and local_business_jsonld() in a template come with the Twig layer.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'local-business');
};
