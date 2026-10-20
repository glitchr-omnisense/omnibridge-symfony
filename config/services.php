<?php

/*
 * The host's contracts (omniscient/omnibridge) bound to Symfony's services,
 * each under its interface's name - an application rebinds one by aliasing
 * that name again. Imported by OmnibridgeBundle and by omnibase's BaseBundle
 * (config/services/core.php, which gives RouterUrls and SecuritySessions
 * omnibase's router). Tagged omnibridge.adapter: ContractsPass removes an
 * adapter whose framework service the application lacks (no SecurityBundle,
 * no CSRF protection).
 *
 * Twig's functions on the contracts (omnibridge/twig's AccessExtension:
 * is_granted on Access, csrf_token on Tokens), the code a page asks under
 * every host: registered after twig-bridge's SecurityExtension and
 * CsrfExtension (TwigBundle adds the bridge's extensions first), whose
 * functions it then answers through the same services; a contract this
 * container lacks leaves its function to the bridge, or to nothing.
 *
 * Not bound here: Locales and Files (an application's languages and storages
 * are its own - omnibase binds its Localizer and Flysystem storages), the
 * clock and the event dispatcher (Symfony's, by their PSR names, when the
 * framework has them).
 */

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Symfony\Contract\CsrfTokens;
use Omnibridge\Symfony\Contract\KernelEnvironment;
use Omnibridge\Symfony\Contract\SecurityAccess;
use Omnibridge\Symfony\Contract\SecuritySessions;
use Omnibridge\Symfony\DependencyInjection\ContractsPass;
use Omnibridge\Symfony\RouterUrls;
use Omnibridge\Symfony\TranslatorTranslations;
use Omnibridge\Twig\AccessExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RouterUrls::class)
        ->args([service('router'), service('assets.packages')->nullOnInvalid()])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Urls::class, RouterUrls::class);

    $services->set(TranslatorTranslations::class)
        ->args([service('translator')])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Translations::class, TranslatorTranslations::class);

    $services->set(KernelEnvironment::class)
        ->args(['%kernel.environment%', '%kernel.debug%', '%kernel.secret%'])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Environment::class, KernelEnvironment::class);

    $services->set(SecurityAccess::class)
        ->args([service('security.authorization_checker'), service('security.token_storage')])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Access::class, SecurityAccess::class);

    $services->set(SecuritySessions::class)
        ->args([service('security.token_storage')])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Sessions::class, SecuritySessions::class);

    $services->set(CsrfTokens::class)
        ->args([service('security.csrf.token_manager')])
        ->tag(ContractsPass::ADAPTER);
    $services->alias(Tokens::class, CsrfTokens::class);

    $services->set(AccessExtension::class)
        ->args([service(Access::class)->nullOnInvalid(), service(Tokens::class)->nullOnInvalid()])
        ->tag('twig.extension');
};
