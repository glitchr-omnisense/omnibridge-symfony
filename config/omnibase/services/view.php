<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The neutral view helpers (plan "omnibase agnostic", F4: Base\View\*) a contract of the Foundation needs
 * (Commons for the Pictures contract, Wysiwyg for Texts), whatever the view engine: the core's declaration
 * (Base\Services, group "view"; step F5e slice 4).
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'view');
};
