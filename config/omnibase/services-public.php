<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->defaults()
        ->public(true);

    // Session
    $services->alias('session.storage.factory.dynamic', 'Base\Security\Session\DynamicSessionStorageFactory');

    // Console
    $services->alias('console', 'Base\Console\Console');
    $services->alias('Base\Console\ConsoleInterface', 'console');

    // Routing
    $services->alias('advanced_router', 'Base\Routing\AdvancedRouter');
    $services->alias('Base\Routing\AdvancedRouterInterface', 'advanced_router');

    // The core's services' public aliases are its declaration's (Base\Services' contracts; plan "omnibase agnostic",
    // step F5e slice 4); these are this bridge's own services'.

    // ParameterBag
    $services->alias('parameter_bag.hot', 'Base\Service\HotParameterBag');
    $services->alias('Base\Service\HotParameterBagInterface', 'parameter_bag.hot');

    // Database
    // Doctrine's where it is there; else the mapping is the core's MetadataManipulator, on the persistence's shapes (plan
    // "omnibase agnostic", steps E3 and B3).
    if (\Omnibridge\Symfony\Omnibase\DependencyInjection\Persistence::hasDoctrine($builder)) {
        $services->alias('doctrine.orm.naming_strategy.camel', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy');
        $services->alias('base.database.metadata_factory', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataFactory');
        $services->alias('base.database.metadata_manipulator', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator');
    } else {
        $services->alias('base.database.metadata_manipulator', 'Base\Database\Mapping\MetadataManipulator');
    }

    // Form
    $services->alias('form.proxy', 'Base\Form\FormProxy');
    $services->alias('Base\Form\FormProxyInterface', 'form.proxy');

    // Admin / Command
    $services->alias('command.sessions.clear', 'Base\Console\Command\CacheClearSessionsCommand');

    // The core's Symfony controllers, moved here with the bundle (plan "omnibase agnostic", step F6), are
    // registered under their new names, which the routes' _controller carries; their former ids stay, as
    // aliases, for a configuration that names one (framework.error_controller, a forward(), a Reference);
    // DemoController's, registered in the demo environment only, beside it (services/demo.php).
    foreach ([
        'SecurityController', 'ProfilerController', 'ErrorController', 'RescueController', 'LocalizerController',
        'WidgetController', 'SitemapController', 'MainController', 'ShortLinkController',
        'UX\AutocompleteController', 'UX\MediaController', 'UX\EditorController', 'Api\ThreadController',
        'Client\NotificationController', 'Client\UserSettingsController', 'Client\ContactController',
        'Client\ThreadSearchController', 'Client\UserSearchController', 'Client\UserProfileController',
    ] as $controller) {
        $services->alias('Base\Controller\\'.$controller, 'Omnibridge\Symfony\Omnibase\Controller\\'.$controller)->public();
    }
};