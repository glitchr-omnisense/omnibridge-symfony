<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Database\Schema\EntitySchema;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Symfony\Omnibase\Console\InstalledEntities;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SchemaNotEmpty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * omnibridge:schema:create under Symfony (omnisense/omnibase's plan "omnibase agnostic", step F7a): the tables of the
 * installed entities created in the application's empty database from their description - omnibase's
 * Base\Database\Schema\EntitySchema and omnisense/omnibridge's SchemaCreator, no SchemaTool -, the same work as the
 * artisan command and the plain-PHP script.
 *
 *     bin/console omnibridge:schema:create [--dump-sql]
 *
 * An adapter: what this host knows is where the entities are (InstalledEntities) and on which database - the
 * connection's PDO and its default table options.
 */
#[AsCommand('omnibridge:schema:create', 'Creates the tables of the installed entities in an empty database, from their description (no ORM\'s schema tool).')]
final class SchemaCreateCommand extends Command
{
    /**
     * @param EntityManagerInterface|null $entityManager Doctrine's where it is there: its entity folders and connection
     * @param object|null                 $connection    omnibridge/eloquent's (Illuminate\Database\Connection) without Doctrine
     *                                                   (plan "omnibase agnostic", step B3): the core's and the application's folders
     */
    public function __construct(
        private readonly ?EntityManagerInterface $entityManager,
        private readonly string $projectDir,
        private readonly ?object $connection = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Prints the statements, one a line, and runs nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $database = InstalledEntities::database($this->entityManager, $this->connection, $this->projectDir);
        if (null === $database) {
            $output->writeln('<error>No database: neither Doctrine\'s entity manager nor omnibridge/eloquent\'s connection is there.</error>');

            return Command::FAILURE;
        }
        ['options' => $options, 'pdo' => $pdo, 'table_options' => $tableOptions] = $database;
        $description = EntitySchema::describe($options['paths'], $options);
        if (!$pdo instanceof \PDO) {
            $output->writeln('<error>The connection is no PDO one: omnibridge:schema:create writes through PDO.</error>');

            return Command::FAILURE;
        }
        $creator = new SchemaCreator($pdo, EntitySchema::columnTypes($options), [
            'charset' => $tableOptions['charset'] ?? 'utf8mb4',
            'collation' => $tableOptions['collate'] ?? $tableOptions['collation'] ?? null,
            'engine' => $tableOptions['engine'] ?? 'InnoDB',
        ]);

        if ($input->getOption('dump-sql')) {
            foreach ($creator->statements($description) as $statement) {
                $output->writeln($statement.';', OutputInterface::OUTPUT_RAW);
            }

            return Command::SUCCESS;
        }

        try {
            $statements = $creator->create($description);
        } catch (SchemaNotEmpty $refusal) {
            $output->writeln('<error>'.$refusal->getMessage().'</error>');

            return Command::FAILURE;
        }
        $output->writeln(\sprintf('%d tables created (%d statements).', \count($description->tables), \count($statements)));

        return Command::SUCCESS;
    }
}
