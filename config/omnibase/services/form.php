<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // A neutral FormDefinition as a Symfony form (plan "omnibase agnostic", step F5b).
    $services->set('Omnibridge\Symfony\Omnibase\Form\DefinitionForms')
        ->public()
        ->args([new Reference('form.factory')]);

    // The core's (Base\Services, group "form"; plan "omnibase agnostic", step F5e slice 4): the neutral layer's unique
    // lookup, the validators, the notifier's channels, the web push, the notifications' null back-office linker, the
    // sign-ins' tracker and its connection subscriber.
    Declarations::group($builder, 'form');

    // ------------------------------
    // Form Extensions
    // ------------------------------
    $services->set('Base\Form\Extension\FormTypeBootstrapExtension')
        ->tag('form.type_extension')
        ->args([new Reference('parameter_bag')]);

    $services->set('Base\Form\Extension\FormTypeCsrfExtension')
        ->tag('form.type_extension');

    $services->set('Base\Form\Extension\FormTypeExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('security.authorization_checker'),
            new Reference('parameter_bag'),
            new Reference('form.factory'),
            new Reference('form.proxy'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('Base\Service\VersionManager'),
            new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set('Base\Form\Extension\FormTypeCollectionExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Base\Form\Extension\FormTypeCollabExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('Base\Service\Collab\CollabRoomResolver'),
            new Reference('security.token_storage'),
            new Reference('security.csrf.token_manager'),
            new Reference('advanced_router'),
            new Reference('Base\Service\Collab\CollabTicketFactory'),
        ]);

    // ------------------------------
    // Controllers, Subscribers, Security
    // ------------------------------
    $controllerServices = [
        'Omnibridge\Symfony\Omnibase\Controller\Client\ThreadSearchController',
        'Omnibridge\Symfony\Omnibase\Controller\Client\UserSearchController',
        'Omnibridge\Symfony\Omnibase\Controller\Client\UserProfileController',
        'Omnibridge\Symfony\Omnibase\Controller\Client\UserSettingsController',
    ];

    foreach ($controllerServices as $controller) {
        $services->set($controller)
            ->autowire(true)
            ->autoconfigure(true)
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber')
            ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')]);
    }

    $services->set('Base\Security\UserChecker')
        ->args([new Reference('Omnibridge\Persistence\UnitOfWork'), new Reference('Base\Notifier\Notifier', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);

    // hwi/oauth-bundle is a suggestion since 4.0: the provider is OAuth-aware when it is there.
    $services->set('Base\Security\UserProvider', interface_exists('HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface') ? 'Base\Security\OAuthUserProvider' : 'Base\Security\UserProvider')
        ->args([
            new Reference('Base\Security\UserTracker'),
            new Reference('App\Repository\UserRepository'),
        ]);

    $services->set('Base\Security\RescueFormAuthenticator')
        ->parent('Base\Security\LoginFormAuthenticator');

    $services->set('Base\Security\LoginFormAuthenticator')
        ->args([
            new Reference('referrer'),
            new Reference('Base\Repository\Users'),
            new Reference('advanced_router'),
            new Reference('security.authorization_checker')
        ]);

    $subscriberServices = [
        'Base\Subscriber\NotifierSubscriber' => [
            'args' => [
                new Reference('base.notifier'),
                new Reference('security.authorization_checker'),
                new Reference('parameter_bag')
            ],
            'bind' => ['$debug' => '%kernel.debug%']
        ],
        'Base\Subscriber\IsGrantedSubscriber' => [
            'args' => [
                new Reference('base.attribute_reader'),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ]
        ],
        'Base\Subscriber\SecuritySubscriber' => [
            'args' => [
                new Reference('App\Repository\UserRepository'),
                new Reference('security.authorization_checker'),
                new Reference('security.token_storage'),
                new Reference('request_stack'),
                new Reference('referrer'),
                new Reference('setting_bag'),
                new Reference('localizer'),
                new Reference('advanced_router'),
                new Reference('parameter_bag'),
                new Reference('base.service.maintenance'),
                new Reference('base.service.launcher'),
                new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('Omnibridge\Contract\Tokens', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                // omnibase 5.0's account rules (Base\Request\Rule\*: the presence and the approval write, the verification's link)
                new Reference('Omnibridge\Persistence\UnitOfWork', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('Omnibridge\Contract\Urls', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]
        ],
        'Base\Subscriber\ReferrerSubscriber' => [
            'args' => [
                new Reference('referrer'),
                new Reference('advanced_router'),
                new Reference('parameter_bag')
            ]
        ],
    ];

    foreach ($subscriberServices as $id => $config) {
        $service = $services->set($id)->tag('kernel.event_subscriber');
        if (isset($config['args'])) {
            $service->args($config['args']);
        }
        if (isset($config['bind'])) {
            foreach ($config['bind'] as $key => $value) {
                $service->bind($key, $value);
            }
        }
    }
};
