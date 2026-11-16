<?php

namespace Base\Console\Command;

use Base\Console\Task\TrashPurge;
use Base\Console\Task\TrashPurgeInput;
use Base\Service\TrashManager;
use DateTime;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** trash:purge: its options to omnibase's TrashPurge task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'trash:purge', aliases: [], description: 'Destroy trashed entities past their grace period')]
class TrashPurgeCommand extends Command
{
    protected TrashManager $trashManager;

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually destroy them (without this, only reports)');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Destroy at most N entries in this run');
    }

    public function setTrashManager(TrashManager $trashManager): self
    {
        $this->trashManager = $trashManager;
        return $this;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TrashPurge($this->trashManager))->run(new TrashPurgeInput((bool) $input->getOption('force'), null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null), Reports::printer($input, $output))->status();
    }
}
