<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use BackupManager\Filesystems\Destination;
use Omnibridge\Symfony\Omnibase\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
        parent::execute($input, $output);

        $storages = $input->getArgument('storages') ?? [];
        $prefix = $input->getOption('prefix') ?? null;
        $cycle = $input->getOption('cycle') ?? -1;
        $id = $input->getOption('id') ?? -1;

        if (!$storages) {
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        if($id < 0) {
            $question = new ConfirmationQuestion('You are about to restore the latest version. Do you wish to continue ? [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Do you wish to restore application from tarball ? [y/N] ', false);
        $restoreApplication = $helper->ask($input, $output, $question);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Do you wish to restore database from tarball ? [y/N] ', false);
        $restoreDatabase = $helper->ask($input, $output, $question);

        return $this->timeMachine->restore($id, $restoreDatabase, $restoreApplication, $storages, $prefix, $cycle);
    }
}
