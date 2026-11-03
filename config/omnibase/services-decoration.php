<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // ParameterBag services (plan "omnibase agnostique", C9): Base\Service\ParameterBag, the container's
    // parameters by a dotted path, decorates Symfony's parameter_bag; HotParameterBag, the settings stored
    // in the database laid over it, decorates it in turn and is `parameter_bag`. omnibase's
    // ParameterBagInterface is that same object, as DependencyInjection's interfaces and
    // Runtime::parameters() (ContractsPass) are - it was the plain ParameterBag, without the settings.
    $services->alias('Base\Service\ParameterBagInterface', 'parameter_bag');

    $services->set('Base\Service\ParameterBag')
        ->parent('parameter_bag')
        ->decorate('parameter_bag')
        ->public(true);

    $services->set('Base\Service\HotParameterBag')
        ->args([service('Base\Service\ParameterBag')])
        ->decorate('parameter_bag')
        ->public(true);

    // Translator services
    $services->alias('Base\Service\TranslatorInterface', 'Base\Service\Translator');

    // Defined from Base\Services (config/services-list.php); under Symfony it decorates the framework's translator.
    $services->get('Base\Service\Translator')
        ->autowire(false)
        ->decorate('translator')
        ->public(true)
        ->tag('twig.runtime')
        ->args([
            new Reference('.inner'),
            // Whether the kernel debugs, through omnibridge's Environment (plan "omnibase agnostic", F5a).
            new Reference('Omnibridge\Contract\Environment'),
            new Reference('parameter_bag'),
            // How the site addresses people (base.translator.politeness: plain, polite, formal - or nothing).
            '%base.translator.politeness%',
        ]);

    // Twig: omnibase's app variable and loader, with Twig only, a suggestion of the core
    // (config/services/twig.php).
    if (class_exists(\Twig\Environment::class)) {
        // Twig AppVariable
        $services->set('Base\Twig\AppVariable')
            ->decorate('twig.app_variable')
            ->public(true)
            ->tag('twig.runtime')
            ->args([
                new Reference('.inner'),
                new Reference('twig.random_variable'),
                new Reference('twig.site_variable'),
                new Reference('twig.email_variable'),
                new Reference('twig.admin_variable'),
                new Reference('setting_bag'),
                new Reference('parameter_bag'),
                new Reference('referrer'),
                new Reference('twig'),
                new Reference('localizer'),
                new Reference('themizer'),
            ]);

        // Twig Loader
        $services->set('Base\Twig\Loader\FilesystemLoader')
            ->decorate('twig.loader.native_filesystem')
            ->args([
                new Reference('.inner'),
                new Reference('parameter_bag'),
                '%kernel.project_dir%',
            ]);
    }

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

    // Twig Environment: with Twig only (config/services/twig.php)
    if (class_exists(\Twig\Environment::class)) {
        $services->set('Base\Twig\Environment')
            ->parent('twig')
            ->decorate('twig')
            ->args([
                new Reference('request_stack'),
                new Reference('localizer'),
                new Reference('advanced_router'),
                new Reference('parameter_bag'),
            ]);
    }

    // API Platform: an App\ override replaces the Base\ resource it extends.
    // Between the attribute/class-name collectors (priority 0) and the cache
    // (-10), so the cached collection is the filtered one. API Platform is a
    // suggestion of the core since 4.0: without its bundle, nothing to decorate,
    // the decorator is dropped (and its class, on API Platform's interface, never loaded).
    $services->set('Base\ApiPlatform\Metadata\Resource\Factory\OverriddenResourceNameCollectionFactory')
        ->decorate('api_platform.metadata.resource.name_collection_factory', null, -5, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            new Reference('.inner'),
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
