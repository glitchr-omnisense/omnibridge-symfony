<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TimeMachineStorage;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TimeMachineStorageCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TimeMachineStorage::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'timemachine:storage', aliases: [], description: '')]
    class TimeMachineStorageCommand extends Legacy
    {
    }
} else {
    /** timemachine:storage: its options to omnibase's TimeMachineStorage task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'timemachine:storage', aliases: [], description: '')]
    class TimeMachineStorageCommand extends Legacy
    {
        public function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TimeMachineStorage($this->timeMachine))->run(Reports::printer($input, $output))->status();
        }
    }
}
