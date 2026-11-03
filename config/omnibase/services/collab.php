<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file contains the real-time collaboration services. These
 * services support EditorType, and also support regular form fields.
 * Current features: the autosave conflict guard. Future features:
 * presence data and live synchronization. This file is a separate
 * domain file. Refer to services.php for the "split per domain"
 * convention.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    $services->set('Base\Service\Collab\CollabRoomResolver')
        ->public(true)
        ->args([
            new Reference('Omnibridge\Persistence\UnitOfWork'),
        ]);

    // This code sets an empty-string default for each parameter. With
    // these defaults, the container still compiles for an app with no
    // deployed relay. The collab_live option stays fully optional. The
    // CollabTicketFactory::isConfigured() method refuses to create a
    // ticket unless both values are set. This method does not sign a
    // ticket silently with an empty value.
    $container->parameters()->set('env(COLLAB_TICKET_SECRET)', '');
    $container->parameters()->set('env(COLLAB_RELAY_WS_URL)', '');

    $services->set('Base\Service\Collab\CollabTicketFactory')
        ->public(true)
        ->args([
            '%env(COLLAB_TICKET_SECRET)%',
            '%env(COLLAB_RELAY_WS_URL)%',
        ]);
};
