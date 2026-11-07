<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection;

use Omnibridge\Persistence\Persistences;
use Omnibridge\Symfony\Omnibase\Persistence\EloquentHost;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The persistence of an omnibase site under Symfony (omnisense/omnibase's plan "omnibase agnostic", step F7d): the
 * option `base.persistence` (config/packages/base.yaml), read when the container is compiled - an environment variable
 * in it is resolved then (`%env(OMNIBASE_PERSISTENCE)%`: a change needs cache:clear) -, chosen as every host chooses it
 * (omnisense/omnibridge's Persistences: the only one installed, else the one named; several installed and none named,
 * refused). The parameter `base.persistence` is the name chosen.
 *
 * Doctrine's wiring is config/omnibase/services/database.php's, as before. Eloquent's, registered here when it is
 * chosen, takes its place behind the same contracts: omnisense/omnibridge's UnitOfWork, Snapshots and Backend, the
 * core's Repositories and each of its repository interfaces (Base\Discovery's `repositories`), the union a deprecated
 * constructor is autowired with; omnibridge/eloquent's persistence reset with the kernel (kernel.reset), as Doctrine's
 * registry resets its entity manager. The users' provider of the firewalls: Compiler\Pass\EloquentPersistencePass.
 * What still names Doctrine's services by their ids (doctrine.orm.entity_manager, a repository's class) stays on
 * Doctrine: listed by the plan's step F7d.
 */
final class Persistence
{
    public const OPTION = 'base.persistence (config/packages/base.yaml)';

    public static function choose(ContainerBuilder $builder, mixed $configured): ?string
    {
        if (\is_string($configured) && '' !== $configured) {
            $configured = $builder->resolveEnvPlaceholders($configured, true);
        }

        return Persistences::choose(\is_string($configured) && '' !== $configured ? $configured : null, self::OPTION);
    }

    public static function register(ContainerBuilder $builder, ?string $persistence): void
    {
        $builder->setParameter('base.persistence', $persistence);
        if (Persistences::ELOQUENT === $persistence) {
            self::eloquent($builder);
        }
    }

    private static function eloquent(ContainerBuilder $builder): void
    {
        $persistence = 'Omnibridge\Eloquent\Omnibase\EloquentPersistence';
        $builder->setDefinition('omnibridge.eloquent.connection', (new Definition('Illuminate\Database\Connection'))
            ->setFactory([EloquentHost::class, 'connection'])
            ->setArguments([new Reference('doctrine.dbal.default_connection', ContainerInterface::NULL_ON_INVALID_REFERENCE), '%env(default::DATABASE_URL)%'])
            // Public: BaseBundle::shutdown() lets its PDO go when it was made.
            ->setPublic(true));

        $services = [];
        foreach (\constant($persistence.'::HOST_SERVICES') as $id) {
            $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        }
        $lifecycle = [
            'empty_trash' => $builder->hasParameter('base.extension.empty_trash') ? '%base.extension.empty_trash%' : '+7 days',
            'max_revisions' => $builder->hasParameter('base.extension.max_revisions') ? '%base.extension.max_revisions%' : 5,
            'entity_dispatchers' => new TaggedIteratorArgument('base.entity_dispatcher'),
        ];
        $builder->setDefinition($persistence, (new Definition($persistence))
            ->setFactory([EloquentHost::class, 'persistence'])
            ->setArguments([
                new Reference('omnibridge.eloquent.connection'),
                new ServiceLocatorArgument($services),
                '%kernel.project_dir%',
                '%kernel.environment%',
                $lifecycle,
                new Reference('doctrine.orm.entity_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'reset']));

        foreach ([
            'Omnibridge\Eloquent\EloquentUnitOfWork' => ['unitOfWork', ['Omnibridge\Persistence\UnitOfWork', 'Omnibridge\Persistence\Snapshots', 'Doctrine\ORM\EntityManagerInterface|Omnibridge\Persistence\UnitOfWork']],
            'Omnibridge\Eloquent\Omnibase\EloquentBackend' => ['backend', ['Omnibridge\Persistence\Backend']],
            'Omnibridge\Eloquent\Omnibase\EloquentRepositories' => ['repositories', ['Base\Database\Repository\Repositories']],
        ] as $id => [$method, $contracts]) {
            $builder->setDefinition($id, (new Definition($id))->setFactory([new Reference($persistence), $method])->setPublic(true));
            foreach ($contracts as $contract) {
                $builder->setAlias($contract, new Alias($id, true));
            }
        }

        // The core's repository interfaces, each the repository on Eloquent implementing it (Users: the application's
        // users, App\Entity\User, as Doctrine's alias names App\Repository\UserRepository).
        foreach (\Omnibridge\Eloquent\Container::interfaces() as $interface => $entity) {
            $builder->setDefinition($interface, (new Definition($interface))
                ->setFactory([new Reference('Omnibridge\Eloquent\Omnibase\EloquentRepositories'), 'implementing'])
                ->setArguments([$interface, $entity])
                ->setPublic(true));
        }
    }
}
