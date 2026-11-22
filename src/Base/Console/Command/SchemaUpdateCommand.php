<?php

namespace Base\Console\Command;

use Base\Console\InstalledEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * omnibridge:schema:update under Symfony (3.2): an existing database brought to the installed entities' description
 * - omnibase's Base\Database\Schema\EntitySchema and omnisense/omnibridge's SchemaCreator::update() (AlterStatements,
 * MySQL and SQLite), no ORM's schema tool -, the same work as the artisan command. For a site without Doctrine, where
 * an omnipack's or the application's upgrade adds a table or a column; with Doctrine, Doctrine Migrations stays the
 * tool that versions the changes.
 *
 *     bin/console omnibridge:schema:update --dump-sql   # prints the statements, runs nothing
 *     bin/console omnibridge:schema:update --force      # runs them
 *
 * The tables the description does not hold are never dropped (doctrine_migration_versions, another tool's); the
 * columns, indexes and foreign keys a described table no longer holds are. Where the entities are and on which
 * database: as omnibridge:schema:create (InstalledEntities).
 */
#[AsCommand('omnibridge:schema:update', 'Brings an existing database to the installed entities\' description: prints the statements (--dump-sql) or runs them (--force), no ORM\'s schema tool.')]
final class SchemaUpdateCommand extends Command
{
    /**
     * @param EntityManagerInterface|null $entityManager Doctrine's where it is there: its entity folders and connection
     * @param object|null                 $connection    omnibridge/eloquent's (Illuminate\Database\Connection) without Doctrine
     * @param array<string, mixed>        $entities      what the container discovered (DiscoveryPass::ENTITIES)
     */
    public function __construct(
        private readonly ?EntityManagerInterface $entityManager,
        private readonly string $projectDir,
        private readonly ?object $connection = null,
        private readonly array $entities = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Prints the statements, one a line, and runs nothing')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Runs the statements');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dump = (bool) $input->getOption('dump-sql');
        $force = (bool) $input->getOption('force');
        if (!$dump && !$force) {
            $output->writeln('<comment>Nothing run: --dump-sql prints the statements, --force runs them.</comment>');

            return Command::INVALID;
        }

        $schema = InstalledEntities::schema($this->entityManager, $this->connection, $this->projectDir, $this->entities);
        if (\is_string($schema)) {
            $output->writeln('<error>'.$schema.'</error>');

            return Command::FAILURE;
        }
        ['description' => $description, 'creator' => $updater] = $schema;

        $statements = $force ? $updater->update($description) : $updater->alterations($description);
        if ($dump) {
            foreach ($statements as $statement) {
                $output->writeln($statement.';', OutputInterface::OUTPUT_RAW);
            }
        }
        if ([] === $statements) {
            $output->writeln('The database is up to date: nothing to run.', $dump ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL);
        } elseif ($force) {
            $output->writeln(\sprintf('The database brought to the description (%d statements).', \count($statements)));
        }

        return Command::SUCCESS;
    }
}
