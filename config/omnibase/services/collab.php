<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * The real-time collaboration (EditorType and the regular fields: the autosave conflict guard): the core's
 * declaration (Base\Services, group "collab"; plan "omnibase agnostic", step F5e slice 4).
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    // env(COLLAB_TICKET_SECRET) and env(COLLAB_RELAY_WS_URL): empty unless the application deploys a relay (Base\Options).
    Declarations::environment($builder, 'collab');

    Declarations::group($builder, 'collab');
};
