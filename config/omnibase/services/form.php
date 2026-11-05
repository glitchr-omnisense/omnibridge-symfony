<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // A neutral FormDefinition as a Symfony form (plan "omnibase agnostic", step F5b).
    $services->set('Omnibridge\Symfony\Omnibase\Form\DefinitionForms')
        ->public()
        ->args([new Reference('form.factory')]);

    $services->set('Base\Form\Definition\UniqueLookup')
        ->args([new Reference('Base\Database\Repository\Repositories')]);

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
    // Validators
    // ------------------------------
    $services->set('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator')
        ->args([new Reference('translator')]);

    $validatorChildren = [
        'Base\Validator\Constraints\AlphanumericValidator',
        'Base\Validator\Constraints\AlphanumericPlusValidator',
        'Base\Validator\Constraints\FileValidator',
        'Base\Validator\Constraints\HexcodeValidator',
        'Base\Validator\Constraints\NotBlankValidator',
    ];

    foreach ($validatorChildren as $child) {
        $services->set($child)->parent('Base\Validator\ConstraintValidator')
            ->tag('validator.constraint_validator');
    }

    $services->set('Base\Validator\ConstraintEntityValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $entityValidators = [
        'Base\Validator\Constraints\UniqueEntityValidator',
        'Base\Validator\Constraints\StringCaseEntityValidator',
    ];

    foreach ($entityValidators as $child) {
        $services->set($child)->parent('Base\Validator\ConstraintEntityValidator')
            ->tag('validator.constraint_validator');
    }

    // Unique (omnisense/omnibase 5.0, step E1 of its plan "omnibase agnostic"): UniqueEntity without Doctrine,
    // the records looked up through the unit of work's Repositories. A 4.x core has no such class.
    if (class_exists('Base\Validator\Constraints\UniqueValidator')) {
        $services->set('Base\Validator\Constraints\UniqueValidator')
            ->args([service('Omnibridge\Persistence\UnitOfWork')])
            ->tag('validator.constraint_validator');
    }

    // ------------------------------
    // Notifier channels
    // ------------------------------
    $services->set('Base\Notifier\Channel\BrowserPlusChannel')
        ->tag('notifier.channel', ['channel' => 'browser+'])
        ->args([new Reference('Omnibridge\Contract\Requests')]);

    $services->set('Base\Notifier\Channel\EmailPlusChannel')
        ->parent('notifier.channel.email')
        ->tag('notifier.channel', ['channel' => 'email+']);

    // Notification center: the persisted list behind the toolbar bell, and
    // Web Push to the browsers that opted in. See each channel's docblock.
    $services->set('Base\Notifier\Channel\InAppChannel')
        ->tag('notifier.channel', ['channel' => 'inapp'])
        ->args([new Reference('Omnibridge\Persistence\UnitOfWork'), new Reference('Base\Repository\Users')]);

    $services->set('Base\Service\Push\WebPushService')->public()
        ->args([
            new Reference('Omnibridge\Persistence\UnitOfWork'),
            new Reference('Base\Repository\User\PushSubscriptionRepository'),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%env(default::VAPID_PUBLIC_KEY)%',
            '%env(default::VAPID_PRIVATE_KEY)%',
            '%env(default::VAPID_SUBJECT)%',
        ]);
    $services->alias('base.push', 'Base\Service\Push\WebPushService');

    // Back-office links for notifications: null here, replaced by the admin
    // bundle's own implementation when it is installed (same id, later
    // definition wins).
    $services->set('Base\Notifier\NullNotificationLinker');
    $services->alias('Base\Notifier\NotificationLinkerInterface', 'Base\Notifier\NullNotificationLinker');

    $services->set('Base\Notifier\Channel\PushChannel')
        ->tag('notifier.channel', ['channel' => 'push'])
        ->args([
            new Reference('Base\Service\Push\WebPushService'),
            new Reference('Base\Notifier\Channel\InAppChannel'),
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

    $services->set('Base\Security\UserTracker')
        ->args([
            new Reference('Omnibridge\Persistence\UnitOfWork'),
            new Reference('request_stack'),
            new Reference('advanced_router'),
            new Reference('Base\Repository\User\ConnectionRepository')
        ]);

    $services->set('Base\EntitySubscriber\ConnectionSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('Base\Security\UserTracker'),
            new Reference('doctrine.orm.entity_manager')
        ]);

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
                new Reference('Omnibridge\Contract\Tokens', ContainerInterface::NULL_ON_INVALID_REFERENCE)
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
