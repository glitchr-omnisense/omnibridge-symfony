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
 * The storages, where omniscient/omnidrive is installed: Omnidrive\Drives over
 * the services tagged flysystem.storage by their storage name - the storages of
 * league/flysystem-bundle's configuration (or omnibase's local ones without
 * it), each read and written as an Omnidrive\Drive.
 *
 * omnibridge/static's build, where it is installed: the console command
 * omnibridge:static:build (Static\BuildCommand), over this kernel and router.
 *
 * Not bound here: Locales and Files (an application's languages and how its
 * files are linked are its own - omnibase binds its Localizer and its
 * FlysystemFiles over these drives), the
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
use Omnibridge\Static\Console\Cli;
use Omnibridge\Symfony\RouterUrls;
use Omnibridge\Symfony\Static\BuildCommand;
use Omnibridge\Symfony\TranslatorTranslations;
use Omnibridge\Twig\AccessExtension;
use Omnidrive\Drives;
use Omnidrive\Flysystem\OperatorDrives;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

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

    if (class_exists(OperatorDrives::class)) {
        $services->set(OperatorDrives::class)
            ->args([tagged_locator('flysystem.storage', 'storage')]);
        $services->alias(Drives::class, OperatorDrives::class)->public();
    }

    // omnibridge/static's build as a console command, where it is installed (bin/console omnibridge:static:build).
    if (class_exists(Cli::class)) {
        $services->set(BuildCommand::class)
            ->args([service('kernel'), service('router')])
            ->tag('console.command');
    }

    $services->set(AccessExtension::class)
        ->args([service(Access::class)->nullOnInvalid(), service(Tokens::class)->nullOnInvalid()])
        ->tag('twig.extension');
};
