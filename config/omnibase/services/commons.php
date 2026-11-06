<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The shared bricks (docs/40-commons): written once, used by the omnibase/* bundles and the applications instead of
 * their own copies. The services are the core's declaration (Base\Services, group "commons"; plan "omnibase agnostic",
 * step F5e slice 4): the opening hours, the calendars, the invitations, the download links, the QR codes, the form and
 * comment guards, the signatures (glitchr/omnisign, where installed), the embeds, the texts rewritten in the back office.
 * Below, what is this bridge's.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'commons');

    $services = $container->services();
    $services->defaults()
        ->public(false)
        ->autowire()
        ->autoconfigure();

    // A TemplatedEmail's {% block subject %} becomes its subject.
    $services->set('Base\Subscriber\TemplatedEmailSubjectSubscriber')
        ->args([service('twig')])
        ->tag('kernel.event_subscriber');
    // The second-level cache emptied after doctrine:fixtures:load and the like.
    $services->set('Base\Subscriber\SecondLevelCacheConsoleSubscriber')
        ->args([service('doctrine')->nullOnInvalid()])
        ->tag('kernel.event_subscriber');
    // The sign-in's captcha after a few failures from an address (base.guard.sign_in_after).
    $services->set('Base\Security\SignInGuard')->public()
        ->arg('$cache', service('cache.app')->nullOnInvalid())
        ->arg('$logger', service('logger')->nullOnInvalid());

    // The rewritten texts' pool (Base\Translation\OverridingTranslator): the shared Redis pool when the application has
    // one (TextOverrideCachePass), so that the web, the worker and cron agree.
    $services->alias('base.text_override.cache', 'cache.app');
};
