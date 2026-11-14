<?php

use Omnibridge\Symfony\Omnibase\DependencyInjection\Declarations;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Persistence;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // Doctrine's wiring only where Doctrine is there (plan "omnibase agnostic", step B3): omnibridge/doctrine installed and
    // DoctrineBundle registered; otherwise the persistence chosen (omnibridge/eloquent, DependencyInjection\Persistence)
    // answers the contracts, and the core's MetadataManipulator the mapping (step E3).
    $doctrine = Persistence::hasDoctrine($builder);

    // Database services
    if ($doctrine) {
        $services->set('Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy')->public();
        $services->set('Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataFactory')->public();

        // Base\Database\Entity\EntityHydrator is defined from Base\Services (config/services-list.php): on the unit
        // of work and its persistence's shapes (plan "omnibase agnostic", step F5c, item 3).

        $services->set('Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator')->public()
            ->args([
                new Reference('doctrine'),
                new Reference('doctrine.orm.entity_manager'),
            ])
            ->bind('$cacheDir', '%kernel.cache_dir%');
    }

    // The core's (Base\Services, group "database"; plan "omnibase agnostic", step F5e slice 4): the attribute reader
    // and the entities' extensions.
    Declarations::group($builder, 'database');

    // Security voters
    $services->set('Omnibridge\Symfony\Omnibase\Security\Voter\AdminVoter')
        ->tag('security.voter')
        ->args([new Reference('advanced_router')]);

    $services->set('Omnibridge\Symfony\Omnibase\Security\Voter\PermissionVoter')
        ->tag('security.voter');

    $services->set('Omnibridge\Symfony\Omnibase\Security\Voter\ComplaintVoter')
        ->tag('security.voter')
        ->args([
            new Reference('security.access.decision_manager'),
            new Reference('security.role_hierarchy'),
        ]);

    $services->set('Omnibridge\Symfony\Omnibase\Security\Voter\AccessVoter')
        ->tag('security.voter')
        ->args([
            new Reference('advanced_router'),
            new Reference('parameter_bag'),
            new Reference('localizer'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
        ]);

    if (!$doctrine) {
        return;
    }

    // The repositories by their interfaces (audit "lesringostars face au master plan", step R15):
    // what the code of a site and of the core type-hints, Doctrine's repository behind. The users'
    // is the application's (App\Repository\UserRepository: the site's own, else omnibase's under
    // that name), as every other service of the core that takes it. Public, as the repositories
    // themselves are handed out by getRepository(): kept when nothing of the container asks them.
    // The list is Base\Repository\Aliases' (step R27), which omnibridge/laravel binds as well.
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineRepositories::class)->args([service('doctrine.orm.entity_manager')]);

    // The unit of work the core's services take in place of the entity manager (plan "omnibase agnostic",
    // step F2d): omnisense/omnibridge's contract, Doctrine's adapter behind it (omnibridge/doctrine's at F3).
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)->args([service('doctrine.orm.entity_manager')]);
    $services->alias(\Omnibridge\Persistence\UnitOfWork::class, \Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)->public();
    // The persistence installed, its scopes (trash, vault) as Doctrine's filters (step F2e).
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineBackend::class)->args([service(\Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)]);
    $services->alias(\Omnibridge\Persistence\Backend::class, \Omnibridge\Doctrine\Omnibase\DoctrineBackend::class)->public();
    foreach (\Base\Repository\Aliases::INTERFACES as $interface => $repository) {
        $services->alias($interface, $repository)->public();
    }
};
