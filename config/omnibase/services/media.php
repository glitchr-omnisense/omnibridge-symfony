<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
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
            new Reference('setting_bag'),
            new Reference('twig'),
            new Reference('translator'),
            new Reference('form.proxy'),
        ]);

    $services->set('Omnibridge\Symfony\Omnibase\Controller\SecurityController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('base.notifier'),
            new Reference('Omnibridge\Persistence\UnitOfWork'),
            new Reference('Base\Repository\User\TokenRepository'),
            new Reference('App\Repository\UserRepository'),
            new Reference('advanced_router'),
            new Reference('form.proxy'),
            new Reference('security.token_storage'),
            new Reference('translator'),
            new Reference('parameter_bag'),
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


    /* ------------------------------
    * Core Services
    * ------------------------------*/

    $services->set('Base\Service\SpamChecker')
        ->public()
        ->args([
            new Reference('Omnibridge\Contract\Requests'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('monolog.http_client'),
            '%kernel.debug%',
            new Reference('Base\Service\FormGuard'),
            new Reference('advanced_router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set('Base\Service\Sitemapper')
        ->public()
        ->args([
            new Reference('Omnibridge\Contract\Views'),
            new Reference('base.attribute_reader'),
            new Reference('advanced_router'),
            new Reference('localizer'),
            // A page left out of the sitemap is said here (Sitemapper::register()).
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);


    /* ------------------------------
    * WYSIWYG Enhancers
    * ------------------------------*/

    $services->set('Base\Service\Model\Wysiwyg\SemanticEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([new Reference('Base\Repository\Layout\SemanticRepository')]);

    $services->set('Base\Service\Model\Wysiwyg\MentionEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('App\Repository\Thread\MentionRepository'),
            new Reference('App\Repository\UserRepository'),
            new Reference('Base\Database\Repository\Repositories'),
            new Reference('obfuscator'),
        ]);

    $services->set('Base\Service\Model\Wysiwyg\LinkEnhancer')
        ->public()
        ->tag('twig.runtime');

    $services->set('Base\Service\Model\Wysiwyg\MediaEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service.image'),
            new Reference('flysystem'),
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);

    $services->set('Base\Service\Model\Wysiwyg\HeadingEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('Omnibridge\Persistence\UnitOfWork'),
            new Reference('slugger'),
        ]);

    $services->set('Base\Service\WysiwygEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('Omnibridge\Contract\Views'),
            new Reference('heading_enhancer'),
            new Reference('semantic_enhancer'),
            new Reference('mention_enhancer'),
            new Reference('Base\Service\Model\Wysiwyg\LinkEnhancer'),
            new Reference('media_enhancer'),
        ]);

    $services->set('Base\Service\EditorEnhancer')
        ->parent('Base\Service\WysiwygEnhancer')
        ->public()
        ->tag('twig.runtime');


    /* ------------------------------
    * Obfuscator System
    * ------------------------------*/

    $services->set('Base\Service\Model\Obfuscator\AbstractCompression')->abstract();

    foreach ([
        'NullCompression',
        'ZlibCompression',
        'GzipCompression',
        'DeflateCompression',
    ] as $class) {
        // Defined from Base\Services (config/services-list.php), as the four below: Symfony adds its tags.
        $services->get("Base\Service\Model\Obfuscator\Compression\\$class")
            ->autowire(false)
            ->tag('obfuscator.compression');
    }

    $services->get('Base\Service\Model\Obfuscator\Compression\HashidsCompression')
        ->autowire(false)
        ->tag('obfuscator.compression')
        ->arg('$secret', '%kernel.secret%');

    // The compressions given by their tag (ObfuscatorCompressionPass), its parameters omnibase's bag.
    $services->get('Base\Service\Obfuscator')
        ->autowire(false)
        ->public()
        ->tag('twig.runtime')
        ->args([new Reference('parameter_bag')])
        ->bind('$cacheDir', '%base.obfuscator.cache_dir%');


    /* ------------------------------
    * File & Media Services
    * ------------------------------*/

    $services->set('Base\Service\FileService')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('Omnibridge\Contract\Views', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('advanced_router'),
            new Reference('obfuscator'),
            new Reference('flysystem'),
            new Reference('parameter_bag'),
        ]);

    // Defined from Base\Services (config/services-list.php); Symfony's services given as FileService's are.
    $services->get('Base\Service\MediaService')
            ->autowire(false)
            ->public()
            ->tag('twig.runtime')
            ->args([
                new Reference('Omnibridge\Contract\Views', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('advanced_router'),
                new Reference('obfuscator'),
                new Reference('flysystem'),
                new Reference('parameter_bag'),
                new Reference('imagine.bitmap'),
                new Reference('imagine.svg'),
                new Reference('profiler', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ]);

    // The storages by name, as omnisense/omnidrive's drives (Omnidrive\Drives, which omnibridge/symfony
    // registers over the services tagged flysystem.storage): those league/flysystem-bundle tags from the
    // application's configuration, or omnibase's local ones without it (FlysystemStoragesPass).
    $services->set('Base\Service\Flysystem')
        ->args([service('Omnidrive\Drives')])
        ->public();


    /* ------------------------------
    * Imagine Adapters
    * ------------------------------*/

    $services->alias('imagine.bitmap', 'imagine.adapter.imagick');
    $services->alias('imagine.svg', 'imagine.adapter.svg');

    $services->set('imagine.meta_data.reader', 'Imagine\Image\Metadata\ExifMetadataReader');

    foreach ([
        'gd'      => 'Imagine\Gd\Imagine',
        'imagick' => 'Imagine\Imagick\Imagine',
        'gmagick' => 'Imagine\Gmagick\Imagine',
        'svg'     => 'Base\Imagine\Svg\Imagine',
    ] as $id => $class) {
        $services->set("imagine.adapter.$id", $class)
            ->call('setMetadataReader', [new Reference('imagine.meta_data.reader')]);
    }


    /* ------------------------------
    * Sharing System
    * ------------------------------*/

    $services->set('Base\Service\Sharing')->public()->tag('twig.runtime');

    $services->set('Base\Service\Model\Sharing\AbstractSharingAdapter')
        ->args([new Reference('Omnibridge\Contract\Views')]);

    foreach ([
        'FacebookAdapter',
        'LinkedInAdapter',
        'GooglePlusAdapter',
        'TumblrAdapter',
        'TwitterAdapter',
        'PinterestAdapter',
    ] as $adapter) {
        $services->set("Base\Service\Model\Sharing\Adapter\\$adapter")
            ->parent('Base\Service\Model\Sharing\AbstractSharingAdapter')
            ->tag('base.service.sharing');
    }


    /* ------------------------------
    * Icon System
    * ------------------------------*/

    $services->set('Base\Service\IconProvider')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.attribute_reader'),
            new Reference('base.service.image'),
            new Reference('localizer'),
            new Reference('advanced_router'),
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter')
        ->tag('base.service.icon')
        ->bind('$metadata', '%kernel.project_dir%/public/bundles/base/metadata/icons.yml')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Service\Model\IconProvider\Adapter\BootstrapTwitterAdapter')
        ->tag('base.service.icon')
        ->bind('$metadata', '%kernel.project_dir%/public/bundles/base/metadata/bootstrap-icons.json')
        ->bind('$cacheDir', '%kernel.cache_dir%');


    /* ------------------------------
    * Trading & Currency
    * ------------------------------*/

    // Live unless the application says otherwise: BASE_TRADING_LIVE=0 keeps
    // every rate to the stored ones but for the calls that ask for the
    // providers (a free plan's monthly quota is spent otherwise).
    $container->parameters()->set('env(BASE_TRADING_LIVE)', 'true');

    $services->set('Base\Service\Trading')
        ->args([new Reference('http_client')])
        ->bind('$cacheDir', '%kernel.cache_dir%')
        ->bind('$live', '%env(bool:BASE_TRADING_LIVE)%');

    $services->set('Base\Service\Model\Currency\AbstractCurrencyApi')
        ->args([new Reference('setting_bag')]);

    foreach ([
        'Fixer',
        'AbstractApi',
        'ExchangeRatesApi',
        'CurrencyLayer',
    ] as $api) {
        $services->set("Base\Service\Model\Currency\Api\\$api")
            ->parent('Base\Service\Model\Currency\AbstractCurrencyApi')
            ->tag('currency.api');
    }
};
