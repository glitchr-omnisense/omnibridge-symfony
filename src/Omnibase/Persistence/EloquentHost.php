<?php

namespace Omnibridge\Symfony\Omnibase\Persistence;

use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Connection;
use Omnibridge\Eloquent\Connections;
use Omnibridge\Eloquent\Omnibase\EloquentPersistence;
use Omnibridge\Symfony\Omnibase\Console\InstalledEntities;
use Psr\Container\ContainerInterface;

/**
 * omnibridge/eloquent as the persistence of a Symfony site (`base.persistence: eloquent`; omnisense/omnibase's plan
 * "omnibase agnostic", step F7d), what the container's factories call (DependencyInjection\Persistence).
 *
 * - The connection: Doctrine's own PDO where DoctrineBundle is installed beside (the same database, the same
 *   transaction: a site's code still on Doctrine's entity manager writes in it too), else Capsule on DATABASE_URL.
 * - The persistence: the installed entities (their folders and target entities as DoctrineBundle maps them, where it
 *   is installed; else the application's src/Entity and the core's), omnibase's lifecycle from the container's
 *   services (EloquentPersistence::host(): a service locator of EloquentPersistence::HOST_SERVICES, the entity
 *   dispatchers tagged base.entity_dispatcher), and the two scopes BaseBundle::boot() enables on Doctrine - the trash,
 *   the vault for the kernel's environment.
 */
final class EloquentHost
{
    public static function connection(?object $doctrine, ?string $url): Connection
    {
        if ($doctrine instanceof \Doctrine\DBAL\Connection) {
            return Connections::pdo($doctrine->getNativeConnection(), (string) $doctrine->getDatabase());
        }
        if (null === $url || '' === $url) {
            throw new \InvalidArgumentException('omnibridge/eloquent has no database: DATABASE_URL is empty and DoctrineBundle gives no connection.');
        }

        return Connections::url($url);
    }

    /**
     * @param array<string, mixed>      $lifecycle OmnibaseListeners' options the container gives (empty_trash, max_revisions, entity_dispatchers)
     * @param iterable<callable>|null   $listeners the services tagged omnibridge.lifecycle_listener (omnisense/omnibridge's
     *                                             Lifecycle\Listeners: a package hearing the writes, step B2), made at the first event
     */
    public static function persistence(Connection $connection, ContainerInterface $services, string $projectDir, string $environment, array $lifecycle = [], ?object $entityManager = null, ?iterable $listeners = null): EloquentPersistence
    {
        $options = $entityManager instanceof EntityManagerInterface && class_exists(InstalledEntities::class)
            ? InstalledEntities::options($entityManager, $projectDir)
            : ['project_dir' => $projectDir, 'paths' => EntitySchema::directories($projectDir)];
        $options['lifecycle'] = $lifecycle;
        $options['listeners'] = $listeners;
        $persistence = EloquentPersistence::host($connection, $services, $options, EntitySchema::describe($options['paths'], $options));
        $persistence->backend()->enableScope('trash');
        $persistence->backend()->enableScope('vault', ['environment' => $environment]);

        return $persistence;
    }
}
