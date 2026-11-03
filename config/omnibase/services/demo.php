<?php

use Base\Demo\DemoMode;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The `demo` environment (docs/20-architecture/demo.md).
 *
 * What declares and creates the demonstration accounts exists everywhere:
 * the fixtures of dev and test build the same accounts. What acts on them -
 * the one-click sign-in, the locks, the headers, the mail that goes nowhere -
 * is registered when the kernel runs `demo`, and only then: in any other
 * environment these services are not in the container at all.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();
    $services->defaults()
        ->public(false)
        ->autowire()
        ->autoconfigure();

    $services->set('Base\Demo\DemoMode')->public();
    $services->set('Base\Demo\DemoAccountRegistry')->public()
        ->arg('$roleHierarchy', service('security.role_hierarchy')->nullOnInvalid());
    $services->set('Base\Demo\DemoAccountFactory')->public()
        ->arg('$doctrine', service('doctrine'));
    $services->set('Base\Demo\DemoGuard')->public()
        ->arg('$doctrine', service('doctrine')->nullOnInvalid());

    // demo_mode() and demo_accounts() in a template: with the Twig layer (config/services/twig.php).

    // Registered everywhere, so that it can say why it refuses outside `demo`.
    $services->set('Base\Console\Command\DemoResetCommand')
        ->arg('$doctrine', service('doctrine')->nullOnInvalid())
        ->arg('$lockFactory', service('lock.factory')->nullOnInvalid())
        ->arg('$secondLevelCache', service('Base\Subscriber\SecondLevelCacheConsoleSubscriber')->nullOnInvalid())
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->tag('console.command');

    if (!$builder->hasParameter('kernel.environment') || DemoMode::ENVIRONMENT !== $builder->getParameter('kernel.environment')) {
        return;
    }

    // The sign-in in one click (its route is declared for `demo` only too).
    $services->set('Omnibridge\Symfony\Omnibase\Controller\DemoController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [service('Psr\Container\ContainerInterface')]);
    // Its former id (omnibase 4.x's class name), as services-public.php aliases the others'.
    $services->alias('Base\Controller\DemoController', 'Omnibridge\Symfony\Omnibase\Controller\DemoController')->public();

    // noindex on every response; no e-mail leaves.
    $services->set('Base\Subscriber\DemoSubscriber')
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->tag('kernel.event_subscriber')
        ->tag('monolog.logger', ['channel' => 'demo']);

    // The back office is not public: its accounts and the super-administrator sign in with a secret each, or not at all.
    $services->set('Base\Subscriber\DemoBackOfficeSubscriber')
        ->tag('kernel.event_subscriber');

    // The same two secrets where omniauth checks the password (config/services/auth.php's stores).
    if (interface_exists('Omniauth\Contract\Passwords')) {
        $services->set('Base\Auth\DemoPasswords')
            ->decorate('Omniauth\Contract\Passwords', null, 0, \Symfony\Component\DependencyInjection\ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([service('.inner'), service('Base\Demo\DemoAccountRegistry'), '%base.demo.superadmin_password%', '%base.demo.admin_password%']);
    }

    // A demonstration account keeps its identifier, address, password and second factor.
    $services->set('Base\DatabaseSubscriber\DemoAccountLockSubscriber')
        ->arg('$translator', service('translator')->nullOnInvalid())
        ->tag('doctrine.event_listener', ['event' => 'preFlush'])
        ->tag('kernel.reset', ['method' => 'reset']);
};
