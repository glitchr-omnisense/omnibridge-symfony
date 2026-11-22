<?php

namespace Omnibridge\Symfony\Tests;

use Base\Console\Command\SchemaCreateCommand;
use Base\Console\Command\SchemaUpdateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * omnibridge:schema:update (3.2) on omnibridge/eloquent's connection, no Doctrine: refused without --dump-sql or
 * --force, a database one table behind brought to the description (omnibridge:schema:create's), the table of another
 * tool kept, none to run once it is up to date.
 */
final class SchemaUpdateCommandTest extends TestCase
{
    public function testAnExistingDatabaseIsBroughtToTheDescription(): void
    {
        if (!class_exists(\Base\Database\Schema\EntitySchema::class) || !interface_exists(\Omnibridge\Schema\Database\AlterStatements::class) || !\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Needs omnisense/omnibase, omnisense/omnibridge 2.1 and pdo_sqlite.');
        }
        $pdo = new \PDO('sqlite::memory:');
        $connection = new class($pdo) {
            public function __construct(private readonly \PDO $pdo)
            {
            }

            public function getPdo(): \PDO
            {
                return $this->pdo;
            }
        };
        $project = sys_get_temp_dir().'/omnibridge-symfony-no-project';

        self::assertSame(0, (new CommandTester(new SchemaCreateCommand(null, $project, $connection)))->execute([]));
        $pdo->exec('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY)');
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('DROP TABLE "thread"');

        $update = new CommandTester(new SchemaUpdateCommand(null, $project, $connection));
        self::assertSame(2, $update->execute([]), 'nothing run without --dump-sql or --force');
        self::assertSame(0, $update->execute(['--dump-sql' => true]));
        self::assertStringContainsString('CREATE TABLE "thread"', $update->getDisplay());
        self::assertStringNotContainsString('doctrine_migration_versions', $update->getDisplay());

        self::assertSame(0, $update->execute(['--force' => true]));
        self::assertStringContainsString('brought to the description', $update->getDisplay());
        self::assertSame(0, $update->execute(['--dump-sql' => true]));
        self::assertSame('', trim($update->getDisplay()), 'up to date');
        self::assertSame('1', (string) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'doctrine_migration_versions'")->fetchColumn());
    }
}
