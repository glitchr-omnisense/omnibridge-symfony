<?php

namespace Base\Console\Command;

use Base\Console\Report;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineArrayUpgrade;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** doctrine:array:upgrade: its options to omnibridge/doctrine's DoctrineArrayUpgrade task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'doctrine:array:upgrade', aliases: [], description: 'This command allows to upgrade array field into json.')]
class DoctrineArrayUpgradeCommand extends Command
{
    protected ?EntityManagerInterface $entityManager;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        ?EntityManagerInterface  $entityManager,
        ParameterBagInterface    $parameterBag
    )
    {
        $this->entityManager = $entityManager;
        parent::__construct($localizer, $translator, null !== $entityManager ? UnitOfWorks::of($entityManager) : null, $parameterBag);
    }

    protected function configure(): void
    {
        $this->SetDescription('Converts array fields in MySQL tables to JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new DoctrineArrayUpgrade($this->entityManager->getConnection()))->run(Reports::printer($input, $output))->status();
    }

    /** @param array{TABLE_NAME: string, COLUMN_NAME: string, DATA_TYPE: string, COLUMN_COMMENT: ?string} $column */
    private function convertArrayFieldToJson(array $column, OutputInterface $output): void
    {
        $tableName = $column['TABLE_NAME'];
        $columnName = $column['COLUMN_NAME'];
        $comment = (string) $column['COLUMN_COMMENT'];

        $isArray = str_contains($comment, '(DC2Type:array)');
        $isJson = strtolower($column['DATA_TYPE']) === 'json' || str_contains($comment, '(DC2Type:json)');
        if (!$isArray && !$isJson) {
            return;
        }

        try {
            $this->convertArrayColumnToJson($tableName, $columnName, $output);
            if ($isArray) {
                $this->alterColumnToJson($tableName, $columnName, $output);
            }
        } catch (\Exception $e) {
            // Handle the exception gracefully
            $output->writeln("Error converting `$columnName` in `$tableName`: " . $e->getMessage());
        }
    }

    private function convertArrayColumnToJson(string $tableName, string $columnName, OutputInterface $output)
    {
        $connection = $this->entityManager->getConnection();
        $query = $connection->createQueryBuilder()
            ->select('id', $columnName)
            ->from($tableName);

        $statement = $query->executeQuery();
        $rows = $statement->fetchAllAssociative();
        foreach ($rows as $row) {

            $value = $row[$columnName];
            if($value == "") $unserializedValue = [];
            else if (is_json($value)) continue;
            else if ($value !== null) {

                // Try to unserialize the value
                $unserializedValue = unserialize($value);
                if ($unserializedValue === false && $value !== 'b:0;') {
                    // If unserialization fails and the value is not 'b:0;', it's likely corrupt
                    $output->writeln("Skipping row in $tableName: Unable to unserialize value in $columnName");
                    continue;
                }
            }

            // Update the value to JSON format
            $jsonValue = json_encode($unserializedValue);
            $connection->executeStatement("UPDATE $tableName SET $columnName = :jsonValue WHERE id = :id", [
                'jsonValue' => $jsonValue,
                'id' => $row['id'],
            ]);
        }

        $output->writeln("Converted $columnName in $tableName to JSON format.");
    }


    private function alterColumnToJson(string $tableName, string $columnName, OutputInterface $output)
    {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->executeStatement("ALTER TABLE $tableName MODIFY $columnName JSON");
            $output->writeln("Altered $columnName in $tableName to JSON format.");
            $output->writeln("--> You can now change attributes/attributes column information in entities to `json`.");
        } catch (\Exception $e) {
            // Handle the exception gracefully
            $output->writeln("Error altering $columnName in $tableName: " . $e->getMessage());
        }
    }

}
