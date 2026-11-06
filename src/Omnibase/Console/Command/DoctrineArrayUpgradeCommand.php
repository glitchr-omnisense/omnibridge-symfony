<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Report;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineArrayUpgrade;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\DoctrineArrayUpgradeCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(Report::class) || !class_exists(DoctrineArrayUpgrade::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'doctrine:array:upgrade', aliases: [], description: 'This command allows to upgrade array field into json.')]
    class DoctrineArrayUpgradeCommand extends Legacy
    {
    }
} else {
    /** doctrine:array:upgrade: its options to omnibridge/doctrine's DoctrineArrayUpgrade task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'doctrine:array:upgrade', aliases: [], description: 'This command allows to upgrade array field into json.')]
    class DoctrineArrayUpgradeCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new DoctrineArrayUpgrade($this->entityManager->getConnection()))->run(Reports::printer($input, $output))->status();
        }
    }
}
