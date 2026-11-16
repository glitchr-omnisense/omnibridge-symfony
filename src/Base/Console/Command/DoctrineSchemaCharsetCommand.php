<?php

namespace Base\Console\Command;

use Base\Console\Report;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineSchemaCharset;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineSchemaCharsetInput;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** doctrine:schema:charset: its options to omnibridge/doctrine's DoctrineSchemaCharset task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'doctrine:schema:charset', aliases: [], description: 'This command allows to update database and table charset and collation.')]
class DoctrineSchemaCharsetCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('update', null, InputOption::VALUE_NONE, 'Update database charset,collate');
        $this->addOption('charset', null, InputOption::VALUE_OPTIONAL, 'Database charset value');
        $this->addOption('collate', null, InputOption::VALUE_OPTIONAL, 'Database collate value');

        $this->addOption('update-tables', null, InputOption::VALUE_NONE, 'Update table collate');
        $this->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Table name');
        $this->addOption('table-collate', null, InputOption::VALUE_OPTIONAL, 'Table collate value');

        parent::configure();
    }

    protected function readDatabaseVariable(string $name): ?string
    {
        $connection = $this->entityManager->getConnection();
        $statement = $connection->prepare("SHOW VARIABLES LIKE '" . $name . "'");

        return $statement->executeQuery()->fetchAllKeyValue()[$name] ?? null;
    }

    protected function writeDatabaseCharsetCollate(string $charset, string $collate): int
    {
        $connection = $this->entityManager->getConnection();
        $params = $connection->getParams();

        $databaseName = $params["dbname"] ?? null;
        if (!$databaseName) {
            return 0;
        }

        $statement = $connection->prepare("ALTER DATABASE " . $databaseName . " CHARACTER SET " . $charset . " COLLATE " . $collate);
        return $statement->executeStatement();
    }

    protected function readTableVariable(string $table, string $name): ?string
    {
        $connection = $this->entityManager->getConnection();
        $statement = $connection->prepare("SHOW TABLE STATUS WHERE NAME LIKE '" . $table . "'");
        return $statement->executeQuery()->fetchAllAssociativeIndexed()[$table][$name] ?? null;
    }

    protected function writeTableCharsetCollate(string $table, string $charset, string $collate): ?int
    {
        $connection = $this->entityManager->getConnection();
        $statement = $connection->prepare("ALTER TABLE " . $table . " CONVERT TO CHARACTER SET " . $charset . " COLLATE " . $collate);
        return $statement->executeStatement();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new DoctrineSchemaCharset($this->entityManager->getConnection()))->run(new DoctrineSchemaCharsetInput(
            (bool) $input->getOption('update'),
            $input->getOption('charset'),
            $input->getOption('collate'),
            (bool) $input->getOption('update-tables'),
            $input->getOption('table'),
            $input->getOption('table-collate'),
        ), Reports::printer($input, $output))->status();
    }
}
