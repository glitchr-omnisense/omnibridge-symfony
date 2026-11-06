<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\DemoReset;
use Base\Console\Task\DemoResetInput;
use Base\Database\UnitOfWorks;
use Omnibridge\Symfony\Omnibase\Console\ApplicationCommands;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\DemoResetCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(DemoReset::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'demo:reset', description: 'Reloads the demonstration: fixtures, uploaded files, caches (demo environment only)')]
    class DemoResetCommand extends Legacy
    {
    }
} else {
    /**
     * demo:reset: omnibase's DemoReset task (its plan, step F5e, slice 3), run with this console's commands
     * (doctrine:fixtures:load, uploader:entities, cache:pool:clear) and Doctrine's second-level cache, the Report
     * printed.
     */
    #[AsCommand(name: 'demo:reset', description: 'Reloads the demonstration: fixtures, uploaded files, caches (demo environment only)')]
    class DemoResetCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $task = new DemoReset(
                $this->mode,
                $this->guard,
                $this->accounts,
                null !== $this->doctrine ? fn () => UnitOfWorks::of($this->doctrine->getManager()) : null,
                $this->lockFactory,
                $this->logger,
                $this->registry,
                null !== $this->secondLevelCache ? $this->secondLevelCache->evict(...) : null,
            );

            return $task->run(new DemoResetInput((bool) $input->getOption('keep-files'), $this->purge, $this->orphans, $this->groups), new ApplicationCommands($this->getApplication(), $output), Reports::printer($input, $output))->status();
        }
    }
}
