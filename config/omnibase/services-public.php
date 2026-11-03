<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(true);

    // Session
    $services->alias('session.storage.factory.dynamic', 'Base\Security\Session\DynamicSessionStorageFactory');

    // Referrer
    $services->alias('referrer', 'Base\Service\Referrer');
    $services->alias('Base\Service\ReferrerInterface', 'referrer');

    // Console
    $services->alias('console', 'Base\Console\Console');
    $services->alias('Base\Console\ConsoleInterface', 'console');

    // Routing
    $services->alias('advanced_router', 'Base\Routing\AdvancedRouter');
    $services->alias('Base\Routing\AdvancedRouterInterface', 'advanced_router');

    // Trading
    $services->alias('trading_market', 'Base\Service\Trading');
    $services->alias('Base\Service\TradingInterface', 'trading_market');

    // Wysiwyg / Editor Enhancers
    $services->alias('wysiwyg_enhancer', 'Base\Service\WysiwygEnhancer');
    $services->alias('Base\Service\WysiwygEnhancerInterface', 'wysiwyg_enhancer');

    $services->alias('editor_enhancer', 'Base\Service\EditorEnhancer');
    $services->alias('Base\Service\EditorEnhancerInterface', 'editor_enhancer');

    // Base services
    $services->alias('base.service', 'Base\Service\BaseService');
    $services->alias('base.service.icon', 'Base\Service\IconProvider');
    $services->alias('base.service.sharing', 'Base\Service\Sharing');

    // Twig renderers: with Twig only, a suggestion of the core (config/services/twig.php)
    if (class_exists(\Twig\Environment::class)) {
        $services->alias('twig.html_renderer', 'Base\Twig\Renderer\Adapter\HtmlTagRenderer');
        $services->alias('twig.webpack_renderer', 'Base\Twig\Renderer\Adapter\WebpackTagRenderer');
    }

    // Media
    $services->alias('base.service.image', 'Base\Service\MediaService');
    $services->alias('Base\Service\MediaServiceInterface', 'base.service.image');

    // TimeMachine: only where glitchr/backup-manager is installed (config/services/core.php)
    if (class_exists(\Backup\Manager\Manager::class)) {
        $services->alias('time_machine', 'Base\Service\TimeMachine');
        $services->alias('Base\Service\TimeMachineInterface', 'time_machine');
    }

    // Cache / Spam / File / Obfuscator
    $services->alias('simple_cache', 'Base\Cache\SimpleCache');
    $services->alias('Base\Cache\SimpleCacheInterface', 'simple_cache');

    $services->alias('spam_checker', 'Base\Service\SpamChecker');
    $services->alias('Base\Service\SpamCheckerInterface', 'spam_checker');

    $services->alias('base.service.file', 'Base\Service\FileService');
    $services->alias('Base\Service\FileServiceInterface', 'base.service.file');

    $services->alias('obfuscator', 'Base\Service\Obfuscator');
    $services->alias('Base\Service\ObfuscatorInterface', 'obfuscator');

    // Wysiwyg model enhancers
    $services->alias('heading_enhancer', 'Base\Service\Model\Wysiwyg\HeadingEnhancer');
    $services->alias('Base\Service\Model\Wysiwyg\HeadingEnhancerInterface', 'heading_enhancer');

    $services->alias('semantic_enhancer', 'Base\Service\Model\Wysiwyg\SemanticEnhancer');
    $services->alias('Base\Service\Model\Wysiwyg\SemanticEnhancerInterface', 'semantic_enhancer');

    $services->alias('mention_enhancer', 'Base\Service\Model\Wysiwyg\MentionEnhancer');
    $services->alias('Base\Service\Model\Wysiwyg\MentionEnhancerInterface', 'mention_enhancer');

    $services->alias('media_enhancer', 'Base\Service\Model\Wysiwyg\MediaEnhancer');
    $services->alias('Base\Service\Model\Wysiwyg\MediaEnhancerInterface', 'media_enhancer');

    // Maintenance / Launcher / Flysystem
    $services->alias('base.service.maintenance', 'Base\Service\MaintenanceProvider');
    $services->alias('Base\Service\MaintenanceProviderInterface', 'base.service.maintenance');

    $services->alias('base.service.launcher', 'Base\Service\Launcher');
    $services->alias('Base\Service\LauncherInterface', 'base.service.launcher');

    $services->alias('flysystem', 'Base\Service\Flysystem');
    $services->alias('Base\Service\FlysystemInterface', 'flysystem');
    // omnibase's storages over omnidrive (step R28), what the core's services type-hint.
    $services->alias('Base\Service\Storages', 'flysystem')->public();

    // Settings / Notifier / Translator / ParameterBag
    $services->alias('setting_bag', 'Base\Service\SettingBag');
    $services->alias('Base\Service\SettingBagInterface', 'setting_bag');

    $services->alias('base.notifier', 'App\Notifier\Notifier');
    $services->alias('Base\Notifier\NotifierInterface', 'base.notifier');

    $services->alias('base.translator', 'Base\Service\Translator');
    $services->alias('Base\Service\TranslatorInterface', 'base.translator');

    $services->alias('parameter_bag.hot', 'Base\Service\HotParameterBag');
    $services->alias('Base\Service\HotParameterBagInterface', 'parameter_bag.hot');

    // Widget / Paginator / Breadgrinder / Sitemap
    $services->alias('base.widget_provider', 'Base\Service\WidgetProvider');
    $services->alias('Base\Service\WidgetProviderInterface', 'base.widget_provider');

    $services->alias('base.paginator', 'Base\Service\Paginator');
    $services->alias('Base\Service\PaginatorInterface', 'base.paginator');

    $services->alias('base.breadgrinder', 'Base\Service\Breadgrinder');
    $services->alias('Base\Service\BreadgrinderInterface', 'base.breadgrinder');

    $services->alias('sitemap', 'Base\Service\Sitemapper');
    $services->alias('Base\Service\SitemapperInterface', 'sitemap');

    // Localizer / Themizer
    $services->alias('localizer', 'Base\Service\Localizer');
    $services->alias('Base\Service\LocalizerInterface', 'localizer');

    $services->alias('themizer', 'Base\Service\Themizer');
    $services->alias('Base\Service\ThemizerInterface', 'themizer');

    // Database
    $services->alias('doctrine.orm.naming_strategy.camel', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy');
    $services->alias('base.database.metadata_factory', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataFactory');

    $services->alias('Base\Database\Entity\EntityHydratorInterface', 'base.database.entity_hydrator');
    $services->alias('base.database.entity_hydrator', 'Base\Database\Entity\EntityHydrator');

    $services->alias('base.database.metadata_manipulator', 'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator');

    // Attributes
    $services->alias('base.attribute_reader', 'Base\Attributes\AttributeReader');

    $services->alias('base.entity_extension', 'Base\Database\Entity\EntityExtension');

    // Twig Variables / Loader: with Twig only (config/services/twig.php)
    if (class_exists(\Twig\Environment::class)) {
        $services->alias('twig.loader.filesystem', 'Base\Twig\Loader\FilesystemLoader');
        $services->alias('twig.random_variable', 'Base\Twig\Variable\RandomVariable');
        $services->alias('twig.site_variable', 'Base\Twig\Variable\SiteVariable');
        $services->alias('twig.email_variable', 'Base\Twig\Variable\EmailVariable');
        $services->alias('twig.admin_variable', 'Base\Twig\Variable\AdminVariable');
    }

    // Form
    $services->alias('form.proxy', 'Base\Form\FormProxy');
    $services->alias('Base\Form\FormProxyInterface', 'form.proxy');

    // Admin / Command
    $services->alias('command.sessions.clear', 'Base\Console\Command\CacheClearSessionsCommand');
};