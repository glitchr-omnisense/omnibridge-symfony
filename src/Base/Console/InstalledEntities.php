<?php

namespace Base\Console;

use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;

/**
 * Where an omnibase application's entities are under Symfony, as omnibase's Base\Database\Schema\EntitySchema takes
 * it (omnibridge:schema:create; omnisense/omnibase's plan "omnibase agnostic", step F7a): what Doctrine's configuration
 * was given - its attribute drivers' paths (the application's mappings, the core's, a bundle's), DoctrineBundle's
 * resolve_target_entities, the enumerations registered with DBAL (a bundle's among them, omnibase/marketplace's
 * order_state). Read, not built: the entity manager is not asked for any metadata. Since 3.2 with what the container
 * discovered (DiscoveryPass's `entities`: every registered omnibase bundle's src/Entity and the base.entity_folders),
 * with Doctrine or without.
 */
final class InstalledEntities
{
    /**
     * EntitySchema's options from this host: the attribute drivers' paths, the project's folder, the targets resolved,
     * the enumerations DBAL has (a bundle's among them), with what the container discovered (discovered()).
     *
     * @param array<string, mixed> $entities DiscoveryPass::ENTITIES
     *
     * @return array<string, mixed>
     */
    public static function options(EntityManagerInterface $entityManager, string $projectDir, array $entities = []): array
    {
        $configuration = $entityManager->getConfiguration();
        $paths = [];
        foreach (self::drivers($configuration->getMetadataDriverImpl()) as $driver) {
            if ($driver instanceof AttributeDriver) {
                $paths = array_merge($paths, $driver->getPaths());
            }
        }

        $targets = [];
        foreach ($entityManager->getEventManager()->getListeners(Events::loadClassMetadata) as $listener) {
            if ($listener instanceof ResolveTargetEntityListener) {
                foreach ((new \ReflectionProperty(ResolveTargetEntityListener::class, 'resolveTargetEntities'))->getValue($listener) as $interface => $mapping) {
                    $targets[$interface] = $mapping['targetEntity'];
                }
            }
        }

        $types = [];
        // The connection's types (omnibridge/doctrine 4.0: DBAL's static registry holds none of omnibase's).
        foreach ($entityManager->getConnection()->getConfiguration()->getTypeProvider() as $name => $type) {
            if (method_exists($type, 'enumeration')) {
                $types[$name] = $type->enumeration();
            }
        }

        return self::discovered(['paths' => array_values(array_unique($paths)), 'project_dir' => $projectDir, 'resolve_target_entities' => $targets, 'types' => $types], $entities);
    }

    /**
     * EntitySchema's options with what DiscoveryPass discovered (its parameter base.database.entities, 3.2): the
     * folders (after Doctrine's, where Doctrine maps them), the files and folders left out, the packages (their
     * enumerations), their interfaces' default targets under the targets resolved already (doctrine.yaml's prevail).
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $entities the parameter: paths, exclude, packages, resolve_target_entities
     *
     * @return array<string, mixed>
     */
    public static function discovered(array $options, array $entities): array
    {
        if ([] === $entities) {
            return $options;
        }
        $options['paths'] = array_values(array_unique([...$options['paths'] ?? [], ...array_keys($entities['paths'] ?? [])]));
        $options['exclude'] = array_values(array_unique([...$options['exclude'] ?? [], ...$entities['exclude'] ?? []]));
        $options['packages'] = ($options['packages'] ?? []) + ($entities['packages'] ?? []);
        $options['resolve_target_entities'] = ($options['resolve_target_entities'] ?? []) + ($entities['resolve_target_entities'] ?? []);

        return $options;
    }

    /**
     * The database omnibridge:schema:create writes to and EntitySchema's options: Doctrine's connection and configuration
     * where Doctrine is there, else omnibridge/eloquent's connection (an Illuminate\Database\Connection) and the core's and
     * the application's entity folders (plan "omnibase agnostic", step B3: Doctrine a suggestion of this bridge); null
     * when neither is there.
     *
     * @param array<string, mixed> $entities DiscoveryPass::ENTITIES
     *
     * @return array{options: array<string, mixed>, pdo: mixed, table_options: array<string, mixed>}|null
     */
    public static function database(?EntityManagerInterface $entityManager, ?object $connection, string $projectDir, array $entities = []): ?array
    {
        if (null !== $entityManager) {
            $dbal = $entityManager->getConnection();

            return ['options' => self::options($entityManager, $projectDir, $entities), 'pdo' => $dbal->getNativeConnection(), 'table_options' => $dbal->getParams()['defaultTableOptions'] ?? []];
        }
        if (null !== $connection && method_exists($connection, 'getPdo')) {
            return ['options' => self::eloquent($projectDir, $entities), 'pdo' => $connection->getPdo(), 'table_options' => []];
        }

        return null;
    }

    /**
     * What omnibridge:schema:create and omnibridge:schema:update work on: the installed entities' description and
     * omnisense/omnibridge's SchemaCreator on the database's PDO, with its table options - or, as a string, why there
     * is none (no database, a connection that is no PDO one).
     *
     * @param array<string, mixed> $entities what the container discovered (DiscoveryPass::ENTITIES)
     *
     * @return array{description: \Omnibridge\Schema\Description, creator: \Omnibridge\Schema\Database\SchemaCreator}|string
     */
    public static function schema(?EntityManagerInterface $entityManager, ?object $connection, string $projectDir, array $entities = []): array|string
    {
        $database = self::database($entityManager, $connection, $projectDir, $entities);
        if (null === $database) {
            return 'No database: neither Doctrine\'s entity manager nor omnibridge/eloquent\'s connection is there.';
        }
        ['options' => $options, 'pdo' => $pdo, 'table_options' => $tableOptions] = $database;
        if (!$pdo instanceof \PDO) {
            return 'The connection is no PDO one: the schema is written through PDO.';
        }

        return [
            'description' => EntitySchema::describe($options['paths'], $options),
            'creator' => new \Omnibridge\Schema\Database\SchemaCreator($pdo, EntitySchema::columnTypes($options), [
                'charset' => $tableOptions['charset'] ?? 'utf8mb4',
                'collation' => $tableOptions['collate'] ?? $tableOptions['collation'] ?? null,
                'engine' => $tableOptions['engine'] ?? 'InnoDB',
            ]),
        ];
    }

    /**
     * EntitySchema's options without Doctrine: the folders discovered (DiscoveryPass's base.database.entities; the core's
     * and the application's src/Entity for a container compiled before 3.2) and what goes with them.
     *
     * @param array<string, mixed> $entities
     *
     * @return array<string, mixed>
     */
    public static function eloquent(string $projectDir, array $entities = []): array
    {
        $options = ['project_dir' => $projectDir, 'paths' => [] === ($entities['paths'] ?? []) ? EntitySchema::directories($projectDir) : []];

        return self::discovered($options, $entities);
    }

    /**
     * The drivers inside omnibase's and omnibridge/doctrine's wrappers and DoctrineBundle's chain.
     *
     * @return list<MappingDriver>
     */
    private static function drivers(?MappingDriver $driver): array
    {
        while (null !== $driver && method_exists($driver, 'getDriver')) {
            $driver = $driver->getDriver();
        }

        if (!$driver instanceof MappingDriverChain) {
            return null === $driver ? [] : [$driver];
        }
        $drivers = [];
        foreach ($driver->getDrivers() as $inner) {
            $drivers = array_merge($drivers, self::drivers($inner));
        }

        return $drivers;
    }
}
