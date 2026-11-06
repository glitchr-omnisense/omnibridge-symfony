<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TrashPurge;
use Base\Console\Task\TrashPurgeInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TrashPurgeCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TrashPurge::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'trash:purge', aliases: [], description: 'Destroy trashed entities past their grace period')]
    class TrashPurgeCommand extends Legacy
    {
    }
} else {
    /** trash:purge: its options to omnibase's TrashPurge task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'trash:purge', aliases: [], description: 'Destroy trashed entities past their grace period')]
    class TrashPurgeCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TrashPurge($this->trashManager))->run(new TrashPurgeInput((bool) $input->getOption('force'), null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null), Reports::printer($input, $output))->status();
        }
    }
}
