<?php

namespace Base\Console\Command;

use App\Notifier\Notifier;
use Base\Console\Task\NotifierOverview;
use Base\Console\Task\NotifierOverviewInput;
use Base\Notifier\Abstract\BaseNotifier;
use Base\Console\Command;
use Base\Console\Reports;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** app:notifier: its options to omnibase's NotifierOverview task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'app:notifier', aliases: [], description: 'This command gives an overview of the templated notifications')]
class NotifierCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new NotifierOverview())->run(new NotifierOverviewInput(), Reports::printer($input, $output))->status();
    }
}
