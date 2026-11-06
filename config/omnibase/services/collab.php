<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The real-time collaboration (EditorType and the regular fields: the autosave conflict guard): the core's
 * declaration (Base\Services, group "collab"; plan "omnibase agnostic", step F5e slice 4).
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    // An empty-string default for each parameter: the container still compiles for an application with no deployed
    // relay, collab_live stays optional, and CollabTicketFactory::isConfigured() refuses a ticket unless both are set.
    $container->parameters()->set('env(COLLAB_TICKET_SECRET)', '');
    $container->parameters()->set('env(COLLAB_RELAY_WS_URL)', '');

    Declarations::group($builder, 'collab');
};
