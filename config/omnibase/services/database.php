<?php

use Base\DependencyInjection\Declarations;
use Base\DependencyInjection\Persistence;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

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
        $services->set('Base\Database\Mapping\NamingStrategy')->public();
        $services->set('Base\Database\Mapping\ClassMetadataFactory')->public();

        // Base\Database\Entity\EntityHydrator is defined from Base\Services (config/services-list.php): on the unit
        // of work and its persistence's shapes (plan "omnibase agnostic", step F5c, item 3).

        $services->set('Base\Database\Mapping\ClassMetadataManipulator')->public()
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
    $services->set('Base\Security\Voter\AdminVoter')
        ->tag('security.voter')
        ->args([new Reference('advanced_router')]);

    $services->set('Base\Security\Voter\PermissionVoter')
        ->tag('security.voter');

    $services->set('Base\Security\Voter\ComplaintVoter')
        ->tag('security.voter')
        ->args([
            new Reference('security.access.decision_manager'),
            new Reference('security.role_hierarchy'),
        ]);

    $services->set('Base\Security\Voter\AccessVoter')
        ->tag('security.voter')
        ->args([
            new Reference('advanced_router'),
            new Reference('parameter_bag'),
            new Reference('localizer'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
        ]);

    // The repositories by their interfaces (Base\Repository\Aliases, which omnibridge/laravel binds as well): what a
    // site's and the core's code type-hint, the agnostic repository behind, on whichever persistence. The users' is
    // the application's (App\Repository\UserRepository: the site's own, else omnibase's under that name).
    foreach (\Base\Repository\Aliases::INTERFACES as $interface => $repository) {
        if (\Base\Database\Repository\Repositories::class !== $interface) {
            $services->alias($interface, $repository)->public();
        }
    }

    // The registry of repositories (Repositories::of()): the application's and the bundles' own classes, registered as
    // services (BaseBundle, tagged base.repository), else the agnostic parent class built for the entity.
    $services->set(\Base\Database\Repository\EntityRepositories::class)
        ->args([service(\Base\Database\Repository\Finders::class), tagged_locator('base.repository')]);
    $services->alias(\Base\Database\Repository\Repositories::class, \Base\Database\Repository\EntityRepositories::class)->public();

    if (!$doctrine) {
        return;
    }

    // Doctrine behind the core's contracts (omnibridge/doctrine's adapters, never named by an application): the
    // repositories' backend and their backend-only statements, the registry of repositories, the unit of work and
    // the persistence's scopes (trash, vault) as Doctrine's filters.
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineFinders::class)->args([service('doctrine')]);
    $services->alias(\Base\Database\Repository\Finders::class, \Omnibridge\Doctrine\Omnibase\DoctrineFinders::class)->public();
    // Doctrine's own getRepository() answers its plain repository (omnibase's repositories take Finders).
    $services->set(\Omnibridge\Doctrine\Omnibase\RepositoryFactory::class);
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)->args([service('doctrine.orm.entity_manager')]);
    $services->alias(\Omnibridge\Persistence\UnitOfWork::class, \Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)->public();
    $services->set(\Omnibridge\Doctrine\Omnibase\DoctrineBackend::class)->args([service(\Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork::class)]);
    $services->alias(\Omnibridge\Persistence\Backend::class, \Omnibridge\Doctrine\Omnibase\DoctrineBackend::class)->public();
};
