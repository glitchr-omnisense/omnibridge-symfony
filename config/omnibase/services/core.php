<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // Form proxy
    $services->set('Base\Form\FormProxy')
        ->public(true)
        ->args([service('form.factory')])
        ->tag('kernel.reset', ['method' => 'reset']);

    // Session storage
    $services->set('Base\Security\Session\DynamicSessionStorageFactory')
        ->public(true)
        ->parent('session.storage.factory.native');

    // Access token handler
    $services->set('Base\Security\AccessTokenHandler')
        ->args([service('Base\Repository\User\TokenRepository')]);

    // Referrer
    $services->set('Base\Service\Referrer')
        ->public(true)
        ->args([
            service('Omnibridge\Contract\Requests'),
            service('advanced_router'),
        ]);

    // Analytics (page views + visitor/user unique counters)
    $services->set('Base\Service\Analytics\UserAgentClassifier')
        ->public(false);

    $services->set('Base\Service\Analytics')
        ->public(true)
        ->args([
            service('Base\Repository\Analytics\PageViewRepository'),
            service('Base\Repository\Analytics\VisitRepository'),
            service('Base\Service\Analytics\UserAgentClassifier'),
            service('Base\Repository\Analytics\PageVisitRepository'),
        ]);

    // CacheClearSessionsCommand
    $services->set('Base\Console\Command\CacheClearSessionsCommand')
        ->parent('Base\Console\Command')
        ->public(true)
        ->tag('console.command')
        ->bind('$projectDir', '%kernel.project_dir%');

    // Controllers
    $controllers = [
        'Omnibridge\Symfony\Omnibase\Controller\MainController' => ['setting_bag'],
        'Omnibridge\Symfony\Omnibase\Controller\ProfilerController' => ['base.notifier', 'App\Repository\UserRepository'],
        'Omnibridge\Symfony\Omnibase\Controller\Client\ContactController' => ['form.proxy', 'base.notifier'],
        'Omnibridge\Symfony\Omnibase\Controller\LocalizerController' => ['localizer', 'Omnibridge\Persistence\UnitOfWork', 'advanced_router', 'referrer', 'translator'],
        'Omnibridge\Symfony\Omnibase\Controller\UX\MediaController' => ['request_stack', 'flysystem', 'base.service.image', 'Base\Repository\Layout\ImageCropRepository'],
        'Omnibridge\Symfony\Omnibase\Controller\UX\AutocompleteController' => ['Base\Service\Obfuscator', 'request_stack', 'trading_market', 'translator', 'Omnibridge\Persistence\UnitOfWork', 'Base\Service\Paginator', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator', 'Base\Field\Search\RemoteSearch', 'base.service.image'],
        'Omnibridge\Symfony\Omnibase\Controller\WidgetController' => ['Base\Repository\Layout\Widget\AttachmentRepository'],
        'Omnibridge\Symfony\Omnibase\Controller\ShortLinkController' => ['advanced_router', 'Base\Repository\Layout\ShortLinkRepository'],
        // The like/follow/publish API. Was silently unregistered (constructor
        // arguments, no service): every /api/thread/{slug}/like answered 500
        // in production - "the like doesn't work".
        'Omnibridge\Symfony\Omnibase\Controller\Api\ThreadController' => ['Omnibridge\Persistence\UnitOfWork', 'translator', 'Base\Repository\ThreadRepository', 'Base\Repository\Thread\LikeRepository'],
        'Omnibridge\Symfony\Omnibase\Controller\Client\NotificationController' => ['Omnibridge\Persistence\UnitOfWork', 'Base\Repository\User\NotificationRepository', 'Base\Service\Push\WebPushService', 'security.csrf.token_manager', 'Base\Notifier\NotificationLinkerInterface'],
    ];

    foreach ($controllers as $id => $args) {
        $definition = $services->set($id)
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber')
            ->call('setContainer', [service('Psr\Container\ContainerInterface')]);
        foreach ($args as $i => $arg) {
            $definition->arg($i, service($arg));
        }
        if ($id === 'Omnibridge\Symfony\Omnibase\Controller\UX\MediaController') {
            $definition->arg(4, new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
        if ($id === 'Omnibridge\Symfony\Omnibase\Controller\UX\AutocompleteController') {
            $definition->arg(9, new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            // An entity whose repository is a plain Doctrine one is searched through omnibase's, built for it.
            $definition->arg(10, service('doctrine'));
        }
    }

    // The editor's endpoints (uploads, mentions, autosave, collab ticket):
    // never registered either - constructor arguments, no service - so every
    // /ux/editorjs/* answered 500, the editor's uploads and autosave included.
    // Autowired: its arguments are all typed.
    $services->set('Omnibridge\Symfony\Omnibase\Controller\UX\EditorController')
        ->autowire()
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [service('Psr\Container\ContainerInterface')]);

    // Subscribers
    $subscribers = [
        'Base\Subscriber\RouterSubscriber' => ['security.authorization_checker', 'advanced_router', 'parameter_bag'],
        'Base\Subscriber\ProfilerSubscriber' => ['advanced_router'],
        'Base\Subscriber\HotParameterBagSubscriber' => ['parameter_bag', 'setting_bag'],
        'Base\Subscriber\AnalyticsSubscriber' => ['security.token_storage', 'advanced_router', 'translator', 'twig', 'App\Repository\UserRepository', '?ga.service'],
        'Base\Subscriber\PageViewSubscriber' => ['Base\Service\Analytics', 'Symfony\Bundle\SecurityBundle\Security', '$excludedPrefixes' => ['/admin', '/_', '/api']],
    ];

    // A service named with a leading "?" is a suggested bundle's (glitchr/ux-google's
    // ga.service, symfony/webpack-encore-bundle's entry point lookup): null without it.
    $optional = static fn (string $id) => str_starts_with($id, '?') ? service(substr($id, 1))->nullOnInvalid() : service($id);

    foreach ($subscribers as $id => $args) {
        $definition = $services->set($id)
            ->tag('kernel.event_subscriber');
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $definition->arg($k, $optional($v));
            } else {
                $definition->bind($k, $v);
            }
        }
    }

    // Request-scoped statics for processes that outlive a request (FrankenPHP
    // worker mode, messenger consumers). Both tags on purpose: kernel.reset never
    // runs on a cloned kernel, kernel.request never runs between messenger
    // messages - see the class docblock.
    $services->set('Base\Subscriber\EntityDispatcherFlushSubscriber')
        ->tag('kernel.event_subscriber');

    $services->set('Base\Subscriber\RequestScopedStateSubscriber')
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    // cache:clear empties what remembers the code: the mapping's metadata, the parsed DQL, the routes
    $services->set('Base\Cache\Clearer\CodeCachesClearer')
        ->args([[
            service('doctrine.orm.default_metadata_cache')->nullOnInvalid(),
            service('doctrine.orm.default_query_cache')->nullOnInvalid(),
            service('cache.adapter')->nullOnInvalid(),
        ]])
        ->tag('kernel.cache_clearer');

    // AdvancedRouter
    $services->set('Base\Routing\AdvancedRouter')
        ->tag('twig.runtime')
        ->tag('kernel.reset', ['method' => 'reset'])
        ->args([
            service('router'),
            service('request_stack'),
            service('security.firewall.map'),
            service('parameter_bag'),
            service('localizer'),
            service('twig.extension.assets'),
            service('cache.adapter'),
        ])
        ->bind('$debug', '%kernel.debug%')
        ->bind('$environment', '%kernel.environment%');

    // Tracks config/routes_paths.yaml (the Base\Attributes\Attribute\Route
    // translationKey dictionary) as a routing resource, so the route cache
    // invalidates when it changes even though no loader reads routes from it.
    $services->set('Base\Routing\Loader\RouteTranslationsResourceLoader')
        ->args([service('file_locator'), '%kernel.environment%'])
        ->tag('routing.loader');

    // The routes of omnibase's controllers, this package's and the core's (`type: omnibase`, step F6).
    $services->set('Omnibridge\Symfony\Omnibase\Routing\Loader\ControllerLoader')
        ->tag('routing.loader');

    // SettingBag
    $services->alias('Base\Service\SettingBagInterface', 'Base\Service\SettingBag');
    $services->set('Base\Service\SettingBag')
        ->public(true)
        ->tag('twig.runtime')
        ->args([
            service('parameter_bag'),
            service('Omnibridge\Persistence\UnitOfWork'),
            service('Base\Repository\Layout\SettingRepository'),
            service('localizer'),
            service('cache.adapter'),
        ])
        ->bind('$environment', '%kernel.environment%');
    // Compiles+persists the settings snapshot at warmup so the first request after a
    // deploy/cache:clear hits a warm cache instead of compiling on-demand (C9: the
    // warmer is the bridge's, SettingBag stands on psr/cache).
    $services->set('Base\Bridge\Symfony\SettingBagWarmer')
        ->args([service('Base\Service\SettingBag')])
        ->tag('kernel.cache_warmer');

    // Reads the administrator's account-security settings and answers "may
    // this user change that themselves". Autowired on SettingBagInterface,
    // which is aliased just above.
    $services->set('Base\\Service\\SecurityPolicy')
        ->autowire()
        ->public(true);

    // Steers a signed-in user to the enrolment prompt while the administrator
    // requires a second factor they do not have.
    $services->set('Base\\Subscriber\\SecurityEnrolmentSubscriber')
        ->autowire()
        ->tag('kernel.event_subscriber');

    // An account made with a password it did not choose (user:create): its owner chooses one first.
    $services->set('Base\\Subscriber\\PasswordChangeSubscriber')
        ->autowire()
        ->tag('kernel.event_subscriber');

    // Emails the account holder when their account is signed into from a
    // browser it has not been signed into before.
    $services->set('Base\\Subscriber\\NewDeviceSubscriber')
        ->autowire()
        ->tag('kernel.event_subscriber');

    // Keeps the SettingBag snapshot in sync with every Setting/SettingIntl
    // write, including the admin CRUD's plain flush() (which bypasses
    // SettingBag::set() entirely). service_closure, NOT service: Doctrine
    // instantiates listeners while initializing the event manager, and
    // constructing SettingBag there (repository -> getClassMetadata) triggers
    // a re-entrant resolveDiscriminator dispatch on the half-initialized
    // event manager.
    $services->set('Omnibridge\Doctrine\Omnibase\EntitySubscriber\SettingSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['event' => 'postUpdate'])
        ->tag('doctrine.event_listener', ['event' => 'postRemove'])
        ->args([service_closure('Base\Service\SettingBagInterface')]);

    // Entity dispatchers shipped by this bundle.
    //
    // BaseExtension::load() calls registerForAutoconfiguration() on
    // EventDispatcherInterface and attaches the six doctrine.event_listener
    // tags to it - but autoconfiguration only ever applies to services that are
    // actually DEFINED somewhere, and these two never were. A consuming app's
    // own dispatchers get picked up by its `App\` resource block, which is why
    // App-side ones worked and these silently did not: neither class was in the
    // container at all, so thread.* and user.* were never dispatched.
    //
    // Concretely, that meant ThreadEvent::PUBLISHABLE never fired, so anything
    // listening for it - such as an app subscriber that mails subscribers when
    // a scheduled article comes due - never ran, with no error to show for it.
    //
    // autoconfigure() is required (the defaults() block above only sets
    // public(false)); autowire() satisfies AbstractEventDispatcher's
    // constructor, exactly as the app-side dispatchers are already resolved.
    $services->set('Base\EntityDispatcher\Event\ThreadEventDispatcher')
        ->autowire()
        ->autoconfigure();

    $services->set('Base\EntityDispatcher\Event\UserEventDispatcher')
        ->autowire()
        ->autoconfigure();

    // The one service that turns eligibility into a capability.
    //
    // Group::isOpenTo() and awardsFor() deliberately grant nothing, so
    // something has to close the gap between "has earned this" and "has it".
    // In fr.latoucheoriginale.www that something is a single service,
    // MarketplaceManager, and this is its counterpart for people.
    $services->set('Base\Service\GroupManager')
        ->autowire()
        ->autoconfigure()
        ->public();

    // Reading and emptying the trash, i.e. everything that has to see what the
    // trash filter hides. Public because the admin trash page and the purge
    // command are its only callers and both fetch it by id.
    $services->set('Base\Service\TrashManager')
        ->public()
        ->args([service('Omnibridge\Persistence\UnitOfWork'), service(\Base\Repository\Extension\TrashBalls::class)]);
    $services->alias('trash_manager', 'Base\Service\TrashManager')->public();

    // Reads the modification history back - for the per-field badge in form
    // labels and for the entity-level history page.
    $services->set('Base\Service\VersionManager')
        ->public()
        ->args([service('Omnibridge\Persistence\UnitOfWork'), service(\Base\Repository\Extension\Revisions::class), '%base.extension.max_revisions%']);
    $services->alias('version_manager', 'Base\Service\VersionManager')->public();

    // ...and the subscriber that HANDLES those events, which is the same bug
    // one layer up: fixing the dispatchers above made thread.publishable fire,
    // but this class was never defined either, so the only listeners left were
    // the consuming app's own. Nothing set the thread's state.
    //
    // The visible symptom was a scheduled article that came due: the app
    // subscriber mailed every newsletter subscriber, this one never flipped
    // STATE_FUTURE to STATE_PUBLISH, and so the article stayed publishable.
    // ThreadPublishableCommand only calls poke(), which touches updatedAt and
    // relies on this subscriber for the actual transition - it even reports
    // "These are now published" - so the cron re-sent the whole newsletter to
    // every subscriber on its next run, every five minutes, indefinitely.
    // Measured on beta before the fix: two runs, two newsletters, 24 queued
    // mails for one article, and the state still STATE_FUTURE.
    //
    // Author and mention notifications live in the same handler, so they were
    // silently dead for the same reason.
    //
    // Its Doctrine hooks were registered nowhere until 2026-11-02 (Marco's decision
    // to turn them on): no reply was followed by its parent's owners, no Mention was
    // ever stored, so thread.mentioned was never mailed. prePersist and onFlush relay
    // to Base\Database\Lifecycle\ThreadParticipants; the enhancer comes in a
    // closure, as SettingSubscriber's SettingBag does: Doctrine builds its listeners
    // when the event manager initialises, and the enhancer's repositories read the
    // mapping there.
    $services->set('Base\EntitySubscriber\ThreadSubscriber')
        ->autowire()
        ->autoconfigure()
        ->arg('$mentionEnhancer', service_closure('Base\Service\Model\Wysiwyg\MentionEnhancerInterface'))
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);

    // The user.* events' listener (UserEventDispatcher raises them): the verification asked at
    // a registration, the approval confirmed, the goodbye of an account closed by its owner, the
    // welcome back of one reopened; preFlush revokes an approval request out of the flush that
    // raised the event (UserSubscriber::preFlush()). Registered in config/services/admin.php
    // until the back office left the core (80088b2d), by nothing since: none of these was sent.
    $services->set('Base\EntitySubscriber\UserSubscriber')
        ->autowire()
        ->autoconfigure()
        ->tag('doctrine.event_listener', ['event' => 'preFlush']);

    // Notifier
    $services->alias('App\Notifier\Notifier', 'Base\Notifier\Notifier');
    $services->alias('Base\Notifier\NotifierInterface', 'Base\Notifier\Notifier');
    $services->set('Base\Notifier\Notifier')
        ->parent('Base\Notifier\Abstract\BaseNotifier')
        ->public(true);

    // No BaseNotifierInterface alias here: aliasing an ABSTRACT definition is
    // unresolvable by design, and once RemoveAbstractDefinitionsPass drops the
    // target the dangling alias crashes framework.test's
    // TestServiceContainerRealRefPass ("Undefined array key"). Autowire against
    // NotifierInterface (aliased to the concrete notifier above) instead.
    $services->set('Base\Notifier\Abstract\BaseNotifier')
        ->abstract(true)
        ->args([
            service('notifier'),
            service('notifier.channel_policy'),
            service('Omnibridge\Persistence\UnitOfWork'),
            service('parameter_bag'),
            service('translator'),
            service('localizer'),
            service('advanced_router'),
            service('Omnibridge\Contract\Views'),
            service('setting_bag'),
        ])
        ->bind('$debug', '%kernel.debug%')
        // what could not be sent is said there (BaseNotifier::send)
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        // the administrators it notifies (step R28: the accounts by their interface)
        ->call('setUsers', [service(\Base\Repository\Users::class)]);

    // LocalCache / CacheWarmers
    $cacheServices = [
        'Base\Cache\Abstract\AbstractLocalCache' => ['$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\SpreadsheetCacheWarmer' => ['$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\IconCacheWarmer' => ['base.service.icon', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\LocalizerCacheWarmer' => ['localizer', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\ThemizerCacheWarmer' => ['themizer', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\AttributeCacheWarmer' => ['base.attribute_reader', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\MetadataCacheWarmer' => ['base.database.metadata_manipulator', 'base.attribute_reader', '$cacheDir' => '%kernel.cache_dir%'],
    ];

    foreach ($cacheServices as $id => $args) {
        $definition = $services->set($id);
        if (str_contains($id, 'Warmer')) {
            $definition->tag('kernel.cache_warmer');
        }
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $definition->arg($k, $optional($v));
            } else {
                $definition->bind($k, $v);
            }
        }
        if (str_contains($id, 'Warmer')) {
            $definition->public(true);
        }
    }

    // Lazy runtime locator backing BaseTrait/BaseCommonTrait static accessors.
    // A compile-time ServiceLocator: constructing it costs nothing (it holds
    // closures), and each service is only instantiated on the FIRST actual
    // static accessor call. Seeded in BaseBundle::boot(), so the statics work
    // in every context (HTTP, console, bare kernel boots) without eagerly
    // building BaseService's full dependency graph. Keys mirror the ids
    // BaseTrait::runtimeGet() requests.
    $services->set('base.runtime', \Symfony\Component\DependencyInjection\ServiceLocator::class)
        ->public(true)
        ->tag('container.service_locator')
        ->args([[
            'base.service' => new Reference('base.service'),
            'setting_bag' => new Reference('setting_bag'),
            'doctrine' => new Reference('doctrine'),
            'base.database.metadata_manipulator' => new Reference('base.database.metadata_manipulator'),
            'security.token_storage' => new Reference('security.token_storage'),
            'request_stack' => new Reference('request_stack'),
            'base.database.entity_hydrator' => new Reference('base.database.entity_hydrator'),
            'base.service.image' => new Reference('base.service.image'),
            'obfuscator' => new Reference('obfuscator'),
            'base.service.icon' => new Reference('base.service.icon'),
            'localizer' => new Reference('localizer'),
            'advanced_router' => new Reference('advanced_router'),
            'security.firewall.map' => new Reference('security.firewall.map'),
            'twig' => new Reference('twig'),
            'base.notifier' => new Reference('base.notifier'),
            'translator' => new Reference('translator'),
            'slugger' => new Reference('slugger'),
            'trading_market' => new Reference('trading_market'),
            'parameter_bag' => new Reference('parameter_bag'),
        ]]);

    // The contracts of the Foundation and their Symfony adapters (plan
    // "omnibase agnostique", C4): what omnibase's framework-free code asks
    // through Base\Contract\Runtime, each bound to its interface's name (an
    // application rebinds one by aliasing that name). The host's contracts are
    // omnisense/omnibridge's since A2 (Omnibridge\Contract\*), bound under
    // that name (their former names, Base\Contract\Urls... and
    // Omnibridge\Twig\Contract\*, are gone since omnibase 4's final pass). The registry gets them
    // from the `base.contracts` locator (ContractsPass), with the framework's
    // clock, event dispatcher and mailer, seeded in BaseBundle::boot().
    //
    // Since A1 the adapters over Symfony's own services are omnibridge/symfony's
    // (Omnibridge\Symfony\*) and so is their wiring: its config/services.php
    // binds Urls, Translations, Environment, Access, Sessions and Tokens, and
    // omnibase gives two of them its own router - addresses through
    // advanced_router, the remember-me cookie's domain from it (their former
    // names, Base\Bridge\Symfony\RouterUrls..., are gone since omnibase 4's
    // final pass). omnibase's own adapters, over its own services
    // (Localizer, Flysystem, MediaService, its router's route names and icons,
    // the screens), stay Base\Bridge\Symfony.
    $container->import(\Omnibridge\Symfony\OmnibridgeBundle::SERVICES);
    $services->get('Omnibridge\Symfony\RouterUrls')
        ->args([service('advanced_router'), service('assets.packages')->nullOnInvalid()]);
    $services->get('Omnibridge\Symfony\Contract\SecuritySessions')
        ->args([service('security.token_storage'), closure([service('advanced_router'), 'getDomain'])]);
    $services->set('Base\Bridge\Symfony\RouterRoutes')
        ->args([service('advanced_router'), service('base.service.icon')->nullOnInvalid()]);
    $services->alias('Omnibridge\Contract\Routes', 'Base\Bridge\Symfony\RouterRoutes');
    // What an entity is called (C4b): the translator's own EntityNouns, over the bundles BaseBundle knows.
    $services->set('Base\Service\Model\EntityNouns')
        ->factory([service('Base\Service\Translator'), 'getEntityNouns']);
    $services->alias('Base\Contract\Nouns', 'Base\Service\Model\EntityNouns');
    $services->set('Base\Bridge\Symfony\LocalizerLocales')
        ->args([service('localizer')]);
    $services->alias('Omnibridge\Contract\Locales', 'Base\Bridge\Symfony\LocalizerLocales');
    $services->set('Base\Bridge\Symfony\FlysystemFiles')
        ->args([service('flysystem'), service('base.service.image')->nullOnInvalid()]);
    $services->alias('Omnibridge\Contract\Files', 'Base\Bridge\Symfony\FlysystemFiles');
    $services->set('Base\Bridge\Symfony\MediaPictures')
        ->args([service('Base\View\Commons')]);
    $services->alias('Base\Contract\Pictures', 'Base\Bridge\Symfony\MediaPictures');
    $services->set('Base\Bridge\Symfony\ServiceMedia')
        ->args([service('base.service.image')]);
    $services->alias('Base\Contract\Media', 'Base\Bridge\Symfony\ServiceMedia');
    $services->set('Base\Bridge\Symfony\WysiwygTexts')
        ->args([service('Base\View\Wysiwyg'), service('editor_enhancer')]);
    $services->alias('Base\Contract\Texts', 'Base\Bridge\Symfony\WysiwygTexts');

    // The screens (plan "omnibase agnostique", C5): a controller hands a
    // template and a view model (Base\Screen\View) to the ScreenResponder,
    // which renders it with the frame of the request (the ContextProvider:
    // a site decorates it to add its own fields) or gives it to a front end
    // in JSON. Stories builds the threads' view models.
    $services->set('Base\Bridge\Symfony\SymfonyScreenContext')
        ->args([
            service('request_stack'),
            service('Omnibridge\Contract\Urls')->nullOnInvalid(),
            service('Omnibridge\Contract\Access')->nullOnInvalid(),
            service('Omnibridge\Contract\Locales')->nullOnInvalid(),
            service('Base\Service\SettingBagInterface')->nullOnInvalid(),
            service('Base\Service\Themizer')->nullOnInvalid(),
            service('twig')->nullOnInvalid(),
            service('twig.app_variable')->nullOnInvalid(),
            service('Omnibridge\Contract\Tokens')->nullOnInvalid(),
        ]);
    $services->alias('Base\Screen\ContextProvider', 'Base\Bridge\Symfony\SymfonyScreenContext')->public();
    $services->set('Base\Bridge\Symfony\ScreenResponder')->public()
        ->args([service('Omnibridge\Contract\Views'), service('Base\Screen\ContextProvider'), '%base.screens.version%']);
    // Actions written once for every host (audit "lesringostars face au master plan", R27): plain
    // classes whose #[Route] the router reads (a site's src/Action; the core's src/Controller/Action, which the
    // routes import of an omnibase site, @BaseBundle/Controller, reads) and
    // whose result - a View, a Template, Json, a Shell, a File, or null for no such page - the
    // ScreenResponder answers; `array $query` is the query string's values, as under Laravel.
    $services->set('Base\Bridge\Symfony\ActionResults')
        ->args([service('Base\Bridge\Symfony\ScreenResponder')])
        ->tag('kernel.event_listener', ['event' => 'kernel.view']);
    $services->set('Base\Bridge\Symfony\ActionArguments')
        ->tag('controller.argument_value_resolver', ['priority' => 50]);
    // The bell's payload (formerly NotificationController::Latest()).
    $services->set('Base\Controller\Action\Notification\Latest')->public()
        ->args([service('Omnibridge\Contract\Access'), service('Base\Repository\User\Notifications'), service('Base\Notifier\NotificationLinkerInterface')->nullOnInvalid()])
        ->tag('controller.service_arguments');
    // The bell's read marks and removals (formerly NotificationController::MarkRead() and Delete()).
    foreach (['Read', 'Delete'] as $action) {
        $services->set('Base\Controller\Action\Notification\\'.$action)->public()
            ->args([service('Omnibridge\Contract\Access'), service('Omnibridge\Contract\Tokens'), service('Base\Repository\User\Notifications')])
            ->tag('controller.service_arguments');
    }
    // The second factor's enrolment page, its "not now" and the e-mailed code turned on or off (formerly
    // UserSettingsController::Enrolment(), EnrolmentSkip(), TwoFactorAuthentication_ToggleEmail()); the session is
    // omniauth's State where omniauth is set up.
    $services->set('Base\Controller\Action\Settings\Enrolment')->public()
        ->args([service('Omnibridge\Contract\Access'), service('Base\Service\SecurityPolicy'), service('Omnibridge\Contract\Urls'), service('Omniauth\Contract\State')->nullOnInvalid()])
        ->tag('controller.service_arguments');
    $services->set('Base\Controller\Action\Settings\EnrolmentSkip')->public()
        ->args([service('Omnibridge\Contract\Access'), service('Omnibridge\Contract\Tokens'), service('Base\Service\SecurityPolicy'), service('Omnibridge\Contract\Urls'), service('Omniauth\Contract\State')->nullOnInvalid()])
        ->tag('controller.service_arguments');
    $services->set('Base\Controller\Action\Settings\EmailCodeToggle')->public()
        ->args([service('Omnibridge\Contract\Access'), service('Omnibridge\Contract\Tokens'), service('Base\Service\SecurityPolicy'), service('Omnibridge\Contract\Urls'), service('Base\Repository\Users')])
        ->tag('controller.service_arguments');
    // A widget's page (formerly WidgetController::Page()).
    $services->set('Base\Controller\Action\Widget\Page')->public()
        ->args([service('Base\Repository\Layout\Widget\PageRepository')])
        ->tag('controller.service_arguments');
    $services->get('Base\Screen\Builder\Stories')->autowire(false)->public() // Base\Services'
        ->args([service('Base\Contract\Texts'), service('Base\Contract\Pictures')]); // "now": Runtime::clock(), the framework's unless one is set by hand
    // The account's screens (C5, its second part): the builder asks the security policy, the
    // Urls and Tokens contracts; AccountScreens, a Twig runtime (it reads Twig's functions:
    // |thumbnail, oauth_available(), push_available()...), hands it what only Symfony knows -
    // registered with the Twig layer (config/services/twig.php).
    $services->set('Base\Screen\Builder\Accounts')->public()
        ->args([service('Base\Service\SecurityPolicy'), service('Omnibridge\Contract\Urls'), service('Omnibridge\Contract\Tokens')]); // "now": Runtime::clock()

    // BaseService: a lazy facade (plan "omnibase agnostique", C9). Lazy, it is a ghost
    // of the class until one of its methods reads a property: injecting it builds
    // none of its 23 services. The core's facade is framework-neutral (plan "omnibase
    // agnostic", step F5c, item 3; Base\Services lists it for every host); under Symfony
    // it is omnibridge/symfony's SymfonyBaseService, its host half (kernel, console, form
    // factory, profiler, Symfony's request, routes and responses).
    $services->get('Base\Service\BaseService')->class(\Omnibridge\Symfony\Omnibase\Service\SymfonyBaseService::class)
        ->autowire(false)
        ->public(true)
        ->lazy()
        ->args([
            service('kernel'),
            service('request_stack'),
            service('security.firewall.map'),
            service('twig'),
            service('slugger'),
            service('doctrine'),
            service('security.authorization_checker'),
            service('security.token_storage'),
            service('security.csrf.token_manager'),
            service('parameter_bag'),
            service('base.notifier'),
            service('form.factory'),
            service('localizer'),
            service('trading_market'),
            service('obfuscator'),
            service('setting_bag'),
            service('base.service.image'),
            service('base.service.icon'),
            service('translator'),
            service('advanced_router'),
            service('base.database.entity_hydrator'),
            service('base.database.metadata_manipulator'),
            new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    // Services inheriting from AbstractLocalCache
    $services->set('Base\Service\Localizer')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true)
        ->tag('twig.runtime')
        ->args([service('parameter_bag'), service('translator')]);

    $services->set('Base\Service\Themizer')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true)
        ->args([service('parameter_bag'), service('security.token_storage')]);

    $services->set('Base\Cache\SimpleCache')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true);

    // Maintenance / Launcher / Paginator / Breadgrinder / WidgetProvider
    $services->set('Base\Service\MaintenanceProvider')
        ->public(true)
        ->args([service('advanced_router'), service('setting_bag'), service('Omnibridge\Contract\Access'), service('parameter_bag'), service('localizer'), service('Omnibridge\Contract\Access')]);

    $services->set('Base\Service\Launcher')
        ->public(true)
        ->args([service('advanced_router'), service('parameter_bag'), service('setting_bag'), service('Omnibridge\Contract\Access'), service('Omnibridge\Contract\Access')]);

    $services->get('Base\Service\Paginator') // Base\Services' (config/services-list.php), on omnibase's router
        ->autowire(false)
        ->public(true)
        ->args([service('advanced_router'), service('parameter_bag')]);

    $services->set('Base\Service\Breadgrinder')
        ->public(true)
        ->args([service('advanced_router'), service('translator'), service('parameter_bag')]);

    $services->set('Base\Service\WidgetProvider')
        ->public(true)
        ->tag('twig.runtime')
        ->args([service(\Base\Repository\Layout\Widgets::class), service(\Base\Repository\Layout\Widget\Slots::class)]);

    // Form extensions
    $services->set('Base\Form\Extension\FormTypeTranslateExtension')->tag('form.type_extension');

    // The forms' guard: the option `guard` (trap, time, lists, captcha) and `spam_protection`
    // (the classifier), Base\Service\FormGuard. FormTypeSpamExtension is folded into it.
    $services->set('Base\Form\Extension\FormTypeGuardExtension')
        ->tag('form.type_extension')
        ->args([
            service('Base\Service\FormGuard'),
            service('spam_checker'),
            service('advanced_router'),
            service('translator')->nullOnInvalid(),
            service('request_stack')->nullOnInvalid(),
        ]);

    // Outgoing-mail trap: a hold-everything switch for the minutes after a
    // hotfix, when you want to see what the site is about to send before it
    // sends it. File-backed under var/ rather than the database or settings,
    // because it has to work in exactly the situations where you distrust the
    // application - and var/cache is wiped on deploy, var/ is not.
    $services->set('Base\Mailer\MailTrap')
        ->public(true)
        ->args(['%kernel.project_dir%/var/mail-trap']);

    $services->set('Base\Subscriber\MailTrapSubscriber')
        ->args([service('Base\Mailer\MailTrap')])
        ->tag('kernel.event_subscriber');

    // Console
    $services->set('Base\Console\Console')->public(true)->args([service('kernel')]);
    $services->set('Base\Console\Command')->public(true)->args([service('localizer'), service('translator'), service('doctrine.orm.entity_manager'), service('parameter_bag')]);

    $commandServices = [
        'Base\Console\Command\NotifierCommand',
        'Base\Console\Command\DoctrineSchemaCharsetCommand',
        'Base\Console\Command\DoctrineArrayUpgradeCommand',
        'Base\Console\Command\BaseMappingCommand',
        'Base\Console\Command\ThreadPublishableCommand',
        'Base\Console\Command\EntityDiscriminatorCommand',
        'Base\Console\Command\NotifierMailTestCommand',
        'Base\Console\Command\MailTrapCommand',
        'Base\Console\Command\TrashPurgeCommand',
        // The developer's lookups: which entities complete what, the enums' icons,
        // the translation keys of entities, enums and CRUD pages.
        'Base\Console\Command\AutocompleteEntitiesCommand',
        'Base\Console\Command\IconEnumsCommand',
        'Base\Console\Command\TranslationCrudCommand',
        'Base\Console\Command\TranslationEntitiesCommand',
        'Base\Console\Command\TranslationEnumsCommand',
    ];

    // The TimeMachine (the backups) and its commands, only where glitchr/backup-manager is
    // installed, a suggestion since 4.0: Base\Service\TimeMachine extends its Manager, and a
    // definition of a class whose parent is missing would fail the container.
    $timeMachine = class_exists(\Backup\Manager\Manager::class);
    if ($timeMachine) {
        array_push($commandServices,
            'Base\Console\Command\TimeMachineSnapshotCommand',
            'Base\Console\Command\TimeMachineSnapshotBackupCommand',
            'Base\Console\Command\TimeMachineSnapshotRestoreCommand',
            'Base\Console\Command\TimeMachineStorageCommand',
        );
    }

    foreach ($commandServices as $command) {
        $definition = $services->set($command)
            ->parent('Base\Console\Command')
            ->tag('console.command');
        // Add specific arguments for certain commands
        if ($command === 'Base\Console\Command\EntityDiscriminatorCommand') {
            $definition->arg(0, service('base.database.metadata_manipulator'));
        }
        if ($command === 'Base\Console\Command\NotifierMailTestCommand') {
            $definition->args([service('base.notifier'), service('App\Repository\UserRepository')]);
        }
        if ($command === 'Base\Console\Command\AutocompleteEntitiesCommand') {
            $definition->args([service('base.database.metadata_manipulator')]);
        }
        if (str_contains($command, 'TimeMachineSnapshot') || $command === 'Base\Console\Command\TimeMachineStorageCommand') {
            $definition->args([service('time_machine'), service('flysystem')]);
        }
        if ($command === 'Base\Console\Command\ThreadPublishableCommand') {
            $definition->call('setThreads', [service(\Base\Repository\Threads::class)]);
        }
        if ($command === 'Base\Console\Command\TrashPurgeCommand') {
            $definition->call('setTrashManager', [service('Base\Service\TrashManager')]);
        }
        // Setters, not args(): args() would replace the parent definition's
        // own four arguments, which every Base\Console\Command constructor
        // still expects.
        //
        // mailer.transports (the real transport chain), not the Mailer
        // service: releasing a held message through Mailer would hand it
        // straight back to the armed trap and capture it again.
        if ($command === 'Base\Console\Command\MailTrapCommand') {
            $definition
                ->call('setMailTrap', [service('Base\Mailer\MailTrap')])
                ->call('setMailerTransport', [service('mailer.transports')]);
        }
    }

    // TimeMachine service (glitchr/backup-manager, above; its aliases in services-public.php,
    // its parent template in services-fix.php, under the same condition).
    if ($timeMachine) {
        $services->set('Base\Service\TimeMachine')
            ->parent('Backup\Manager\Manager')
            ->public(true)
            ->args([service('flysystem'), service('doctrine'), service('parameter_bag')]);
    }

    // Console commands
    $services->set('Base\Console\Command\UserNotificationCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command');

    $services->set('Base\Console\Command\UserCreateCommand')
        ->autowire()
        ->tag('console.command');

    $services->set('Base\Console\Command\UploaderEntitiesCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->call('setRepositories', [service('Base\Database\Repository\Repositories')]);

    $services->set('Base\Console\Command\UploaderImagesCommand')
        ->parent('Base\Console\Command\UploaderEntitiesCommand')
        ->tag('console.command')
        ->args([
            new Reference('base.service.image'),
            new Reference('Omnibridge\Symfony\Omnibase\Controller\UX\MediaController'),
        ]);

    $services->set('Base\Console\Command\UploaderImagesCropCommand')
        ->parent('Base\Console\Command\UploaderImagesCommand')
        ->tag('console.command');

    $services->set('Base\Console\Command\TranslationControllersCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command');

    $services->set('Base\Console\Command\TranslationSettingsCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([new Reference('setting_bag')]);

    $services->set('Base\Console\Command\IconEntitiesCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Base\Console\Command\DoctrineDatabaseImportCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([
            new Reference('base.database.entity_hydrator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.notifier'),
        ]);
};
