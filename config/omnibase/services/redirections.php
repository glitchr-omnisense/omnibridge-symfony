<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The addresses a site no longer answers (docs/40-commons/redirections.md): Base\Entity\Layout\Redirection,
 * looked up when a request ends in "not found". Redirections is the core's declaration (Base\Services, group
 * "redirections"; plan "omnibase agnostic", step F5e slice 4); its subscriber is this bridge's.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'redirections');

    $services = $container->services();
    $services->defaults()
        ->public(false)
        ->autowire()
        ->autoconfigure();

    $services->set('Base\Subscriber\RedirectionSubscriber');
};
