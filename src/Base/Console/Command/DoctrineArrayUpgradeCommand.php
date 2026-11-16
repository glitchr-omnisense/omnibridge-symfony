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
}
