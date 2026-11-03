<?php


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults();

    // Backup Manager — only a DI-parent template for Base\Service\TimeMachine;
    // its 3-arg constructor is never wired directly, so keep it abstract.
    // glitchr/backup-manager is a suggestion since 4.0 (config/services/core.php).
    if (class_exists(\Backup\Manager\Manager::class)) {
        $services->set('Backup\Manager\Manager')
            ->abstract();
    }

    // Notifier fix
    $services->alias('Symfony\Component\Notifier\Channel\ChannelPolicyInterface', 'notifier.channel_policy');

    // Twig fix: with Twig only, a suggestion of the core (config/services/twig.php)
    if (class_exists(\Twig\Environment::class)) {
        $services->set('Symfony\Bridge\Twig\Extension\AssetExtension')
            ->tag('twig.runtime')
            ->args([new Reference('assets.packages')]);
    }

    // Doctrine fix
    $services->alias('Doctrine\Bundle\DoctrineBundle\ConnectionFactory', 'doctrine.dbal.connection_factory');

    $services->set('Doctrine\Persistence\Mapping\ClassMetadataFactory')
        ->factory([new Reference('doctrine.orm.default_entity_manager'), 'getMetadataFactory']);
};