<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // ParameterBag services (plan "omnibase agnostique", C9): Base\Service\ParameterBag, the container's
    // parameters by a dotted path, decorates Symfony's parameter_bag; HotParameterBag, the settings stored
    // in the database laid over it, decorates it in turn and is `parameter_bag`. omnibase's
    // ParameterBagInterface is that same object, as DependencyInjection's interfaces and
    // Runtime::parameters() (Base\Discovery's `contracts`) are - it was the plain ParameterBag, without the settings.
    $services->alias('Base\Service\ParameterBagInterface', 'parameter_bag');

    $services->set('Base\Service\ParameterBag')
        ->parent('parameter_bag')
        ->decorate('parameter_bag')
        ->public(true);

    $services->set('Base\Service\HotParameterBag')
        ->args([service('Base\Service\ParameterBag')])
        ->decorate('parameter_bag')
        ->public(true);


    // The core's (Base\Services, group "decoration"; plan "omnibase agnostic", step F5e slice 4): omnibase's app variable,
    // Twig loader and environment around Twig's (with Twig only), API Platform's resource names an App\ override filters.
    Declarations::group($builder, 'decoration');

    // Form services
    $services->alias('Base\Form\FormFactoryInterface', 'Base\Form\FormFactory');

    $services->set('Base\Form\FormFactory')
        ->parent('form.factory')
        ->decorate('form.factory')
        ->args([
            new Reference('validator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
        ]);

    // The file links of the error pages and of the profiler, on the developer's
    // machine when the site runs in a container (APP_PATH): Symfony's own
    // FileLinkFormatter on a mapped format (omnibridge/symfony's Omnibridge\Symfony\FileLinks).
    $services->set('base.debug.file_link_formatter', \Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter::class)
        ->decorate('debug.file_link_formatter', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->factory([\Omnibridge\Symfony\FileLinks::class, 'formatter'])
        ->args([
            new Reference('.inner'),
            '%debug.file_link_format%',
            '%kernel.project_dir%',
            '%env(default::APP_PATH)%',
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    // Console commands
    $services->set('Base\Console\Command\CacheClearCommand')
        ->parent('Base\Console\Command')
        ->decorate('console.command.cache_clear')
        ->tag('console.command')
        ->args([
            new Reference('.inner'),
            new Reference('command.sessions.clear'),
            new Reference('flysystem'),
            new Reference('base.notifier'),
            new Reference('advanced_router'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$cacheDir', '%kernel.cache_dir%');
};
