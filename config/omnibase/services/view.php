<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The neutral view helpers (plan "omnibase agnostic", F4: Base\View\*), registered whatever the view
 * engine: the logic of omnibase's Twig functions and filters, which the Twig extensions call
 * (config/services/twig.php, with Twig only), and which a screen's contracts, a Blade view or a
 * React page's props read without Twig. Those a contract of the Foundation needs are services here;
 * the others are built by their extension, or by hand (new Base\View\Formats...), as they take
 * only what they are given.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()
        ->public(false);

    // The shared bricks and a stored picture to its URL (the Pictures contract:
    // Base\Bridge\Symfony\MediaPictures, config/services/core.php).
    $services->set('Base\View\Commons')
        ->args([
            service('Base\Service\Embeds'),
            service('Base\Service\Qr\QrCode'),
            service('Base\Service\OpeningHours'),
            closure([service('assets.packages'), 'getUrl']),
            service('Base\Service\MediaService')->nullOnInvalid(),
            '%kernel.project_dir%/public',
        ]);

    // A stored text rendered (the Texts contract: Base\Bridge\Symfony\WysiwygTexts).
    $services->set('Base\View\Wysiwyg')
        ->args([service('wysiwyg_enhancer'), service('editor_enhancer')]);
};
