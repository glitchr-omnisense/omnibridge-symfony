<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotBackupInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TimeMachineSnapshotBackupCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

if (!class_exists(TimeMachineSnapshot::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'timemachine:snapshot:backup', aliases: [], description: '')]
    class TimeMachineSnapshotBackupCommand extends Legacy
    {
    }
} else {
    /** timemachine:snapshot:backup: its options to omnibase's TimeMachineSnapshot task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'timemachine:snapshot:backup', aliases: [], description: '')]
    class TimeMachineSnapshotBackupCommand extends Legacy
    {
        public function execute(InputInterface $input, OutputInterface $output): int
        {
            $this->timeMachine->setCommandOutput($output);
            $helper = $this->getHelper('question');

            return (new TimeMachineSnapshot($this->timeMachine, $this->flysystem))->backup(
                new TimeMachineSnapshotBackupInput((array) $input->getArgument('storages'), $input->getOption('database'), $input->getOption('prefix'), (int) ($input->getOption('cycle') ?? -1), (bool) $input->getOption('batch'), (bool) $input->getOption('userlog')),
                static fn (string $question, bool $default): bool => (bool) $helper->ask($input, $output, new ConfirmationQuestion($question, $default)),
                Reports::printer($input, $output),
            )->status();
        }
    }
}
