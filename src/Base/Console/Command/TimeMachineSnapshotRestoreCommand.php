<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotRestoreInput;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/** timemachine:snapshot:restore: its options to omnibase's TimeMachineSnapshot task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'timemachine:snapshot:restore', aliases: [], description: '')]
class TimeMachineSnapshotRestoreCommand extends TimeMachineSnapshotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->timeMachine->setCommandOutput($output);
        $helper = $this->getHelper('question');

        return (new TimeMachineSnapshot($this->timeMachine, $this->flysystem))->restore(
            new TimeMachineSnapshotRestoreInput((array) $input->getArgument('storages'), $input->getOption('database'), $input->getOption('prefix'), (int) ($input->getOption('cycle') ?? -1), (int) ($input->getOption('id') ?? -1)),
            static fn (string $question, bool $default): bool => (bool) $helper->ask($input, $output, new ConfirmationQuestion($question, $default)),
            Reports::printer($input, $output),
        )->status();
    }
}
