<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        $connection = $this->entityManager->getConnection();
        $params = $connection->getParams();

        $schemaManager = $connection->createSchemaManager();
        $databaseName = $params["dbname"] ?? null;

        $tableName = $input->getOption("table");
        $tableNames = $tableName ? [$tableName] : $schemaManager->listTableNames();

        $update = $input->getOption("update");
        $updateTables = $input->getOption("update-tables");

        $charsetDefault = $this->readDatabaseVariable("character_set_database");
        $charset = $input->getOption("charset") ?? $params["charset"] ?? $charsetDefault;

        $collateDefault = $this->readDatabaseVariable("collation_database");
        $collate = $input->getOption("collate") ?? $params["defaultTableOptions"]["collate"] ?? $collateDefault;

        $output->writeln("");
        $updateDb = $update && (($charset != $charsetDefault) || ($collate != $collateDefault));
        if (!$updateDb) {
            $output->writeln("<info>Database selected:</info> " . $databaseName);
            $output->section()->writeln("\t- Charset: <warning>" . $charset . "</warning>");
            $output->section()->writeln("\t- Collation: <warning>" . $collate . "</warning>");
        } else {
            $output->section()->writeln("\nUpdating database schema...");
            $this->writeDatabaseCharsetCollate($charset, $collate);

            $msg = ' [OK] Database `' . $databaseName . '` altered.. (charset, collate)=(' . $charset . ', ' . $collate . ')';

            $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
        }

        if (!$updateTables) {
            $output->writeln("<info>Table list:</info> ");
            foreach ($tableNames as $tableName) {
                $output->section()->writeln("\t- " . $tableName . "; Collation = <warning>" . $this->readTableVariable($tableName, "Collation") . "</warning>");
            }
        } else {
            $output->section()->writeln("\nUpdating table schema...");

            $count = 0;
            $tableCollate = $input->getOption("table-collate") ?? $tableCollateDefault ?? $params["defaultTableOptions"]["collate"];
            foreach ($tableNames as $tableName) {
                $count += $this->writeTableCharsetCollate($tableName, $charset, $collate);
            }

            $msg = ' [OK] ' . $count . ' table column(s) altered.. (charset, collate)=(' . $charset . ', ' . $tableCollate . ') ';
            $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
        }

        $output->section()->writeln("");
        return Command::SUCCESS;
    }
}
