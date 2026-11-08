<?php

namespace Omnibridge\Symfony\Omnibase\Console;

use Base\Database\Schema\EntitySchema;
use Doctrine\DBAL\Types\Type;
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
 * order_state). Read, not built: the entity manager is not asked for any metadata.
 */
final class InstalledEntities
{
    /**
     * EntitySchema's options from this host: the attribute drivers' paths, the project's folder, the targets resolved,
     * the enumerations DBAL has (a bundle's among them).
     *
     * @return array<string, mixed>
     */
    public static function options(EntityManagerInterface $entityManager, string $projectDir): array
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
        foreach (Type::getTypeRegistry()->getMap() as $name => $type) {
            if (method_exists($type, 'enumeration')) {
                $types[$name] = $type->enumeration();
            }
        }

        return ['paths' => array_values(array_unique($paths)), 'project_dir' => $projectDir, 'resolve_target_entities' => $targets, 'types' => $types];
    }

    /**
     * The database omnibridge:schema:create writes to and EntitySchema's options: Doctrine's connection and configuration
     * where Doctrine is there, else omnibridge/eloquent's connection (an Illuminate\Database\Connection) and the core's and
     * the application's entity folders (plan "omnibase agnostic", step B3: Doctrine a suggestion of this bridge); null
     * when neither is there.
     *
     * @return array{options: array<string, mixed>, pdo: mixed, table_options: array<string, mixed>}|null
     */
    public static function database(?EntityManagerInterface $entityManager, ?object $connection, string $projectDir): ?array
    {
        if (null !== $entityManager) {
            $dbal = $entityManager->getConnection();

            return ['options' => self::options($entityManager, $projectDir), 'pdo' => $dbal->getNativeConnection(), 'table_options' => $dbal->getParams()['defaultTableOptions'] ?? []];
        }
        if (null !== $connection && method_exists($connection, 'getPdo')) {
            return ['options' => ['project_dir' => $projectDir, 'paths' => EntitySchema::directories($projectDir)], 'pdo' => $connection->getPdo(), 'table_options' => []];
        }

        return null;
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
