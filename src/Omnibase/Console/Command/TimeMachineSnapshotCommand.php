<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TimeMachineSnapshotCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TimeMachineSnapshot::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'timemachine:snapshot', aliases: [], description: '')]
    class TimeMachineSnapshotCommand extends Legacy
    {
    }
} else {
    /** timemachine:snapshot: its options to omnibase's TimeMachineSnapshot task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'timemachine:snapshot', aliases: [], description: '')]
    class TimeMachineSnapshotCommand extends Legacy
    {
        public function execute(InputInterface $input, OutputInterface $output): int
        {
            $this->timeMachine->setCommandOutput($output);

            return (new TimeMachineSnapshot($this->timeMachine, $this->flysystem))->run(new TimeMachineSnapshotInput((array) $input->getArgument('storages'), $input->getOption('database'), $input->getOption('prefix'), (int) ($input->getOption('cycle') ?? -1)), Reports::printer($input, $output))->status();
        }
    }
}
