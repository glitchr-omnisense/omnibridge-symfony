<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The core's services are its declaration (Base\Services, plan "omnibase agnostic", steps F5a and F5e slice 4): data
 * that Symfony's container, Laravel's and Omnibridge\Container read alike. omnibridge/symfony compiles each group in its
 * place among its own definitions (Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations): the first group here,
 * loaded first by BaseExtension, each other one by config/omnibase/services/<group>.php and services-decoration.php,
 * so that the services a tag collects keep their order. tests/ServicesTest checks that Symfony's container answers it.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'services');
};
