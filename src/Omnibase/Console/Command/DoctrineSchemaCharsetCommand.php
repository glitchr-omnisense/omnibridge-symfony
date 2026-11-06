<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Report;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineSchemaCharset;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineSchemaCharsetInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\DoctrineSchemaCharsetCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(Report::class) || !class_exists(DoctrineSchemaCharset::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'doctrine:schema:charset', aliases: [], description: 'This command allows to update database and table charset and collation.')]
    class DoctrineSchemaCharsetCommand extends Legacy
    {
    }
} else {
    /** doctrine:schema:charset: its options to omnibridge/doctrine's DoctrineSchemaCharset task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'doctrine:schema:charset', aliases: [], description: 'This command allows to update database and table charset and collation.')]
    class DoctrineSchemaCharsetCommand extends Legacy
    {
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
}
