<?php

use Base\Twig\Renderer\TagRendererInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;

/*
 * omnibase's Twig layer (src/Twig): its environment, loader, variables, tag renderers and
 * extensions, and the Symfony services that only mean something with them. Optional (plan "omnibase
 * agnostic", F4; Marco: the core's Twig extensions are there when Twig is used and take nothing away
 * when it is not): twig/twig is a suggestion of the core, and without it nothing here is registered,
 * on the TimeMachine's model (config/services/core.php). The logic of the functions and filters is
 * the neutral helpers' (Base\View\*), which need no Twig and are what Blade and a React page call.
 *
 * services-public.php (the twig.* aliases), services-decoration.php (the environment, its loader and
 * its app variable) and services-fix.php (Symfony's AssetExtension) register their Twig part under
 * the same condition.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!class_exists(\Twig\Environment::class)) {
        return;
    }

    $services = $container->services();

    $services->defaults()
        ->public(false);

    // omnibridge's Views over Twig's environment (plan "omnibase agnostic", F5a): what the core's services
    // render through - the notifier and its mails, the sitemap, the QR sheet, the sharing buttons, the
    // media tags, the editor, the screens. The environment asked when first needed (its extensions take
    // some of those services). omnibridge/twig is required by omnibridge/symfony; an application binds
    // its own Views over this alias to render them with another engine.
    if (class_exists(\Omnibridge\Twig\TwigViews::class)) {
        $services->set('Omnibridge\Twig\TwigViews')->args([service_closure('twig')]);
        $services->alias('Omnibridge\Contract\Views', 'Omnibridge\Twig\TwigViews')->public();
        // A constructor that still accepts Twig's environment, deprecated (until 6.0), beside Views.
        $services->alias('Omnibridge\Contract\Views|Twig\Environment', 'Omnibridge\Contract\Views');
    }

    // An asset's path as the page links it (Base\View\AssetPaths): omnibase's environment, which decorates `twig`,
    // answers it - what the Symfony field types read (FileType's thumbnail) in place of Twig's environment (step F5c).
    $services->alias('Base\View\AssetPaths', 'twig');

    // The tag renderers, handed to omnibase's environment (TagRendererPass).
    $builder->registerForAutoconfiguration(TagRendererInterface::class)->addTag('twig.tag_renderer');

    // Twig services
    $services->set('Base\Twig\AssetPackage')->public()
        ->tag('assets.package', ['package' => 'base.assets.package'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Twig\Variable\SiteVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('sitemap'),
            new Reference('translator'),
            new Reference('localizer'),
            new Reference('setting_bag'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
            new Reference('kernel'),
        ]);

    $services->set('Base\Twig\Variable\EmailVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('setting_bag'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Twig\Variable\RandomVariable')->public()
        ->tag('twig.variable');

    $services->set('Base\Twig\Variable\AdminVariable')->public()
        ->parent('Base\Twig\Variable\SiteVariable')
        ->tag('twig.runtime')
        ->tag('twig.variable')
        ->args([new Reference('Base\Routing\AdminUrlGeneratorInterface')]);

    $services->set('Base\Twig\Extension\MediaTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('Omnibridge\Symfony\Omnibase\Controller\UX\MediaController'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\SemanticTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('semantic_enhancer')]);

    $services->set('Base\Twig\Extension\TradingTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('trading_market')]);

    // Twig renderer adapters
    $services->set('Base\Twig\Renderer\AbstractTagRenderer')
        ->abstract()
        ->args([
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('slugger'),
            new Reference('parameter_bag'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\HtmlTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\WebpackTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('webpack_encore.entrypoint_lookup_collection', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages'),
        ])
        ->bind('$publicDir', '%kernel.project_dir%/public')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Twig\Extension\WebpackTwigExtension')
        ->tag('twig.extension', ['priority' => -1]);

    // The bell, framework-free: the host's Access (the user signed in) and Tokens (its token).
    $services->set('Base\Twig\Extension\NotificationTwigExtension')
        ->tag('twig.extension')
        ->args([
            service('Omnibridge\Contract\Access'),
            new Reference('Base\Repository\User\NotificationRepository'),
            new Reference('Base\Service\Push\WebPushService'),
            service('Omnibridge\Contract\Tokens'),
            new Reference('Base\Notifier\NotificationLinkerInterface'),
        ]);

    // omnibase's framework-free extensions (audit "lesringostars face au
    // master plan", R24), which omnibridge/laravel registers too: what a
    // template shows of a stored file (MediaService), links, icons,
    // enumerations, stored texts. The functions and filters they carry were
    // MediaTwigExtension's, FunctionTwigExtension's, TranslatorTwigExtension's
    // and HtmlTwigExtension's, under the same names.
    $services->set('Base\Twig\Extension\PictureTwigExtension')->public()
        ->tag('twig.extension')
        ->args([service('Base\Service\MediaService'), service('Base\Service\FileService')]);
    $services->set('Base\Twig\Extension\LinkTwigExtension')
        ->factory([service('Base\Twig\Extension\MediaTwigExtension'), 'links'])
        ->tag('twig.extension');
    $services->set('Base\Service\Icons')
        ->factory([service('Base\Service\IconProvider'), 'icons']);
    $services->set('Base\Twig\Extension\IconTwigExtension')
        ->tag('twig.extension')
        ->args([service('Base\Service\Icons')]);
    $services->set('Base\Twig\Extension\EnumTwigExtension')
        ->tag('twig.extension')
        ->args([service('Base\Service\Translator')]);
    $services->set('Base\Twig\Extension\TextsTwigExtension')
        ->tag('twig.extension')
        ->args([service('Base\Contract\Texts')]);

    $services->set('Base\Twig\Extension\HtmlTwigExtension')
        ->tag('twig.extension')
        ->args([
            new Reference('twig.html_renderer'),
            new Reference('wysiwyg_enhancer'),
            new Reference('editor_enhancer'),
        ]);

    // omnibridge/twig's helpers (html_attributes, mb_ucfirst, second,
    // join_if_exists, less_than, html_decode...), the ones another framework
    // rendering these templates registers: added first (the highest
    // priority), so a filter of the same name registered by omnibase's own
    // extensions - str_shorten, highlight, datetime, lang, country,
    // lang_name, which do more - is the one a template gets.
    $services->set('Omnibridge\Twig\HelperExtension')
        ->tag('twig.extension', ['priority' => 255])
        ->args(['%kernel.default_locale%']);

    $services->set('Base\Twig\Extension\FunctionTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('translator'),
            new Reference('twig.extension.assets'),
            new Reference('twig'),
            new Reference('Base\Routing\AdminUrlGeneratorInterface'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$routeRegistry', new Reference('Base\Admin\Router\AdminRouteRegistry', ContainerInterface::NULL_ON_INVALID_REFERENCE));

    $services->set('Base\Twig\Extension\AccountTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\OAuthTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('hwi_oauth.resource_ownermap_locator', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
    $services->set('Base\Twig\Extension\MathTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClassTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClipboardTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\SecurityPolicyTwigExtension')
        ->tag('twig.extension')
        ->args([service('Base\Service\SecurityPolicy')]);
    $services->set('Base\Twig\Extension\AnalyticsTwigExtension')
        ->tag('twig.extension')
        ->args([service('Base\Service\Analytics')]);
    $services->set('Base\Twig\Extension\FormTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('form.proxy')]);
    $services->set('Base\Twig\Extension\WidgetTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.widget_provider')]);
    $services->set('Base\Twig\Extension\LocalizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\ThemizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('themizer')]);
    // The language and theme menus, framework-free: the host's Locales contract and the Themizer.
    $services->set('Base\Twig\Extension\MenuTwigExtension')
        ->tag('twig.extension')
        ->args([service('Omnibridge\Contract\Locales'), new Reference('themizer')]);
    $services->set('Base\Twig\Extension\TranslatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\PaginatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('translator')]);
    $services->set('Base\Twig\Extension\BreadgrinderTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.breadgrinder')]);
    $services->set('Base\Twig\Extension\SharingFilter')
        ->tag('twig.extension')
        ->args([new Reference('base.service.sharing')]);
    $services->set('Base\Twig\Extension\DeployVersionExtension')
        ->tag('twig.extension')
        ->bind('$projectDir', '%kernel.project_dir%');

    // The demonstration (demo_mode(), demo_accounts()) and schema.org's LocalBusiness
    // (local_business(), local_business_jsonld()), autowired as in config/services/demo.php and
    // local-business.php.
    $services->set('Base\Twig\Extension\DemoTwigExtension')
        ->autowire()
        ->tag('twig.extension');
    $services->set('Base\Twig\Extension\LocalBusinessTwigExtension')
        ->autowire()
        ->tag('twig.extension');

    // CommonsTwigExtension: embed_url(), qr_code(), opening_hours(), |picture (Base\View\Commons,
    // which the screens' Pictures contract is without Twig: config/services/commons.php).
    $services->set('Base\Twig\Extension\CommonsTwigExtension')
        ->autowire()
        ->arg('$media', service('Base\Service\MediaService')->nullOnInvalid())
        ->tag('twig.extension');

    // The account's screens' Symfony side (C5): a Twig runtime, which asks Twig's functions and
    // filters by their names (|thumbnail, oauth_available(), push_available()...).
    $services->set('Base\Bridge\Symfony\AccountScreens')->public()
        ->args([service('Base\Screen\Builder\Accounts'), service('twig'), service('Omnibridge\Contract\Access')->nullOnInvalid()])
        ->tag('twig.runtime');

    // The Symfony host's classes of the Twig layer, which leave for omnibridge/symfony at F5c: the
    // kernel subscriber that adds the renderers' tags to a page, the warmer of the Webpack
    // renderer's entry points, the form extension that loads a field's entry.
    $services->set('Base\Subscriber\TwigSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            service('twig.html_renderer'),
            service('twig.webpack_renderer'),
            service('security.authorization_checker'),
            service('parameter_bag'),
            service('advanced_router'),
        ]);

    $services->set('Base\Cache\Warmer\WebpackCacheWarmer')->public()
        ->tag('kernel.cache_warmer')
        ->args([
            service('parameter_bag'),
            service('twig.webpack_renderer'),
            service('webpack_encore.entrypoint_lookup[_default]')->nullOnInvalid(),
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%')
        ->bind('$publicDir', '%kernel.project_dir%/public');

    $services->set('Base\Form\Extension\FormTypeWebpackExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('form.proxy'),
            new Reference('twig.webpack_renderer'),
        ]);
};
