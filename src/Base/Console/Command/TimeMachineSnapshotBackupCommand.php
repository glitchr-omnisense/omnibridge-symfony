<?php

namespace Base\Console\Command;

use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotBackupInput;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/** timemachine:snapshot:backup: its options to omnibase's TimeMachineSnapshot task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'timemachine:snapshot:backup', aliases: [], description: '')]
class TimeMachineSnapshotBackupCommand extends TimeMachineSnapshotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
        $this->addOption('batch', null, InputOption::VALUE_NONE, 'Do you run batch command ?', null);
        $this->addOption('userlog', null, InputOption::VALUE_NONE, 'Save user info too?', null);
    }

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
