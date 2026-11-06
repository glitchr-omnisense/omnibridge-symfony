<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // Controllers
    $services->set('Omnibridge\Symfony\Omnibase\Controller\ErrorController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('error_handler.error_renderer.html'),
            new Reference('advanced_router'),
            new Reference('Omnibridge\Contract\Environment'),
            new Reference('request_stack'),
            new Reference('profiler', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ]);

    $services->set('Omnibridge\Symfony\Omnibase\Controller\SitemapController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')]);

    $services->set('Omnibridge\Symfony\Omnibase\Controller\RescueController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('advanced_router'),
            new Reference('twig'),
            new Reference('form.proxy'),
            new Reference('Base\Auth\Account\RescuePage'),
        ]);

    $services->set('Omnibridge\Symfony\Omnibase\Controller\SecurityController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        // The account's rules, written once for every host (omnibase's Base\Auth\Account, plan "omnibase agnostic",
        // step F5e slice 5), then what stays Symfony's.
        ->args([
            new Reference('Base\Auth\Account\SignIn'),
            new Reference('Base\Auth\Account\SignOutPage'),
            new Reference('Base\Auth\Account\AlreadySignedIn'),
            new Reference('Base\Auth\Account\Verification'),
            new Reference('Base\Auth\Account\Closing'),
            new Reference('Base\Auth\Account\Passwords'),
            new Reference('Base\Auth\Account\WaitingPages'),
            new Reference('advanced_router'),
            new Reference('form.proxy'),
            new Reference('parameter_bag'),
            new Reference('referrer'),
        ]);

    // Subscribers
    $services->set('Base\Subscriber\LocalizerSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('localizer'),
            new Reference('advanced_router'),
            new Reference('security.token_storage'),
        ]);

    // Former EagerSubscriber: eager construction is obsolete now that
    // AttributeReader's constructor is metadata-free (heavy precompute moved
    // to AttributeCacheWarmer) — only the cache-valid marker remains.
    $services->set('Base\Subscriber\ValidCacheSubscriber')
        ->tag('kernel.event_subscriber');

    $services->set('Base\Subscriber\FlashBagSubscriber')
        ->tag('kernel.event_subscriber');

    // Database Subscribers
    $services->set('Omnibridge\Doctrine\Omnibase\Database\Middleware\PlatformMiddleware')
        ->tag('doctrine.middleware');

    // The schema managers of every connection that takes DoctrineBundle's default factory: on MySQL,
    // a SET column read with its values and the "(DC2Type:...)" comments of before left out (doctrine/dbal
    // as published, plan "omnibase agnostique" C6b). Nothing to decorate without DoctrineBundle.
    $services->set('Omnibridge\Doctrine\Omnibase\SchemaManagerFactory')
        ->decorate('doctrine.dbal.default_schema_manager_factory', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([new Reference('.inner')]);

    $services->set('Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\AttributeSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata', 'priority' => 4096])
        ->tag('doctrine.event_listener', ['event' => 'resolveDiscriminator', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postLoad', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'prePersist', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preUpdate', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preRemove', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postPersist', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postUpdate', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postRemove', 'priority' => 2048])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.attribute_reader'),
        ]);

    /* ------------------------------
    * Doctrine Subscribers
    * ------------------------------*/

    $services->set('Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\IntlSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata',    'priority' => 4096])
        ->tag('doctrine.event_listener', ['event' => 'resolveDiscriminator', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onQuery',              'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postLoad',             'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'prePersist',           'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preFlush',             'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onFlush',              'priority' => 2048])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('localizer'),
        ]);

    // Low priority: after the subscribers that still change associations in onFlush.
    $services->set('Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\InverseCollectionCacheSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'onFlush', 'priority' => -1024]);

    $services->set('Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\TrackingPolicySubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Omnibridge\Doctrine\Omnibase\EntitySubscriber\ExtensionSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.entity_extension'),
        ]);

    // Soft deletion, and the modification history that goes with it. Both run
    // BELOW AttributeSubscriber's 2048 so Timestamp and Blameable have already
    // stamped updatedAt and the initiator by the time a revision reads the
    // change set; Trasheable runs above Versionable so a removal that turns
    // into a soft delete never also reads as an edit.
    $services->set('Omnibridge\Doctrine\Omnibase\EntitySubscriber\TrasheableSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'onFlush',   'priority' => 1024])
        ->tag('doctrine.event_listener', ['event' => 'postFlush', 'priority' => 1024])
        ->args([
            '%base.extension.empty_trash%',
        ]);

    $services->set('Omnibridge\Doctrine\Omnibase\EntitySubscriber\VersionableSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'onFlush',   'priority' => 512])
        ->tag('doctrine.event_listener', ['event' => 'postFlush', 'priority' => 512])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            '%base.extension.max_revisions%',
        ]);

    // The core's (Base\Services, group "media"; plan "omnibase agnostic", step F5e slice 4): the spam checker, the sitemap,
    // the text enhancers, the compressions' parent, the files, the storages, the SVG Imagine, the sharing and its
    // adapters, the icons and their adapters, the trading and its currency APIs. After the Doctrine listeners above.
    Declarations::group($builder, 'media');

    /* ------------------------------
    * Imagine Adapters (the bitmap ones; the SVG one, imagine.svg, is the core's)
    * ------------------------------*/

    $services->alias('imagine.bitmap', 'imagine.adapter.imagick');

    $services->set('imagine.meta_data.reader', 'Imagine\Image\Metadata\ExifMetadataReader');

    foreach ([
        'gd'      => 'Imagine\Gd\Imagine',
        'imagick' => 'Imagine\Imagick\Imagine',
        'gmagick' => 'Imagine\Gmagick\Imagine',
    ] as $id => $class) {
        $services->set("imagine.adapter.$id", $class)
            ->call('setMetadataReader', [new Reference('imagine.meta_data.reader')]);
    }

    // env(BASE_TRADING_LIVE): live unless the application says otherwise, read by the core's Trading (Base\Options).
    Declarations::environment($builder, 'media');
};
