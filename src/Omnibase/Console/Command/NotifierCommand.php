<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\NotifierOverview;
use Base\Console\Task\NotifierOverviewInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\NotifierCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(NotifierOverview::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'app:notifier', aliases: [], description: 'This command gives an overview of the templated notifications')]
    class NotifierCommand extends Legacy
    {
    }
} else {
    /** app:notifier: its options to omnibase's NotifierOverview task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'app:notifier', aliases: [], description: 'This command gives an overview of the templated notifications')]
    class NotifierCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new NotifierOverview())->run(new NotifierOverviewInput(), Reports::printer($input, $output))->status();
        }
    }
}
