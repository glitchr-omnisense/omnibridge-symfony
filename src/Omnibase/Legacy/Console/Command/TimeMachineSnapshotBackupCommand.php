<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
        parent::execute($input, $output);

        $storages  = $input->getArgument('storages') ?? [];
        $database  = $input->getOption('database')   ?? null;
        $batchMode = $input->getOption('batch')      ?? false;
        $userlog  = $input->getOption('userlog')    ?? false;
        $prefix    = $input->getOption('prefix')     ?? null;
        $cycle     = $input->getOption('cycle')      ?? -1;

        if (!$storages) {
            $output?->section()->writeln("Please select a storage. aborted.");
            return Command::FAILURE;
        }

        if($userlog) {
            $output->section()->writeln("<info>User configuration will be included in the backup</info>\n");
        } else {
            $output->section()->writeln("<warning>User configuration will not be included in the backup</warning> (use `--userlog` option to include it)\n");
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('You are about to backup this application and its database. Do you wish to continue [Y/n] ? ', false);
        if ($batchMode) $output?->section()->writeln("<warning>Batch mode enabled..</warning>\n");
        if(!$batchMode && !$helper->ask($input, $output, $question))
            return Command::SUCCESS;

        if($this->timeMachine->backup($database, $storages, $userlog, $prefix, $cycle)) {
            $output->section()->writeln("<info>Backup completed successfully.</info>\n");
            return Command::SUCCESS;
        }
            
        $output->section()->writeln("<error>Backup failed.</error>\n");
        return Command::FAILURE;
    }
}
