<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * omniauth on omnibase's tables (plan "omniauth", §2.2, steps AU4 and AU7): where omnisense/omniauth is installed
 * (it is only suggested), each of its stores bound by its contract's name to Base\Auth\'s, the e-mails sent by
 * omnibase's Notifier (NotifierSender), Base\Auth\Policy around omniauth's second-factor rule, PolicyLinking for
 * omniauth's address linking, and the listeners of omniauth's events. omnibridge/symfony's OmniauthExtension
 * (config/packages/omniauth.yaml) reads them. The services are the core's declaration (Base\Services, group
 * "auth", which requires omniauth's Accounts; plan "omnibase agnostic", step F5e slice 4).
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    Declarations::group($builder, 'auth');
};
