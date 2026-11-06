<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Report;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineDatabaseImport;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineDatabaseImportInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\DoctrineDatabaseImportCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

if (!class_exists(Report::class) || !class_exists(DoctrineDatabaseImport::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'doctrine:database:import', aliases: [], description: 'This command allows to import data from an XLS file into the database')]
    class DoctrineDatabaseImportCommand extends Legacy
    {
    }
} else {
    /** doctrine:database:import: its options to omnibridge/doctrine's DoctrineDatabaseImport task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'doctrine:database:import', aliases: [], description: 'This command allows to import data from an XLS file into the database')]
    class DoctrineDatabaseImportCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $helper = $this->getHelper('question');
            $bar = null;

            return (new DoctrineDatabaseImport($this->entityManager, $this->classMetadataManipulator, $this->entityHydrator, $this->translator, $this->notifier))->run(
                new DoctrineDatabaseImportInput(
                    $input->getArgument('path'),
                    null !== $input->getOption('spreadsheet') ? explode(',', (string) $input->getOption('spreadsheet')) : null,
                    $input->getOption('extension'),
                    (int) $input->getOption('entries'),
                    (int) $input->getOption('skip'),
                    (int) $input->getOption('batch'),
                    (bool) $input->getOption('notify'),
                    (bool) $input->getOption('force'),
                    (bool) $input->getOption('on-the-fly'),
                ),
                Reports::printer($input, $output),
                static fn (string $question): ?string => $helper->ask($input, $output, new Question($question)),
                static fn (string $question, array $choices): array => (array) $helper->ask($input, $output, (new ChoiceQuestion($question, $choices))->setMultiselect(true)),
                static function (string $step, int $total = 0) use (&$bar, $output): void {
                    match ($step) {
                        'start' => ($bar = new ProgressBar($output, $total))->start(),
                        'advance' => $bar?->advance(),
                        default => $bar?->finish(),
                    };
                },
            )->status();
        }
    }
}
