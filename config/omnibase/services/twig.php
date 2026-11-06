<?php

use Base\Twig\Renderer\TagRendererInterface;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
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

    // The tag renderers, handed to omnibase's environment: the core's registries (Base\Discovery's `autoconfigure` and
    // `collect`, step F5e slice 4c, compiled by BaseExtension and DiscoveryPass); here for a core without them (4.x).
    if (!class_exists(\Base\Discovery::class)) {
        $builder->registerForAutoconfiguration(TagRendererInterface::class)->addTag('twig.tag_renderer');
    }

    // The core's (Base\Services, group "twig", which requires Twig; plan "omnibase agnostic", step F5e slice 4): the asset
    // package, the variables, the tag renderers, the extensions (omnibridge/twig's HelperExtension among them, in its
    // place), the account's screens. The tag renderers are handed to omnibase's environment by their tag (above).
    Declarations::group($builder, 'twig');

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
