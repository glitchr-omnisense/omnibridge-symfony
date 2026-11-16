<?php

namespace Base\Console\Command;

use Base\Console\Task\ThreadPublishable;
use Base\Console\Task\ThreadPublishableInput;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\RowLocks;
use Base\Enum\ThreadState;
use Base\Repository\Threads;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** thread:publishable: its options to omnibase's ThreadPublishable task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'thread:publishable', aliases: [], description: '')]
class ThreadPublishableCommand extends Command
{
    /** The threads (step R28: by their interface, where it asked the entity manager's repository). */
    /** @var Threads<\Base\Entity\Thread> */
    protected Threads $threads;

    /** @param Threads<\Base\Entity\Thread> $threads */
    public function setThreads(Threads $threads): self
    {
        $this->threads = $threads;

        return $this;
    }

    protected function configure(): void
    {
        $this->addOption('publish', null, InputOption::VALUE_NONE, 'Should I publish them ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new ThreadPublishable($this->threads, $this->unitOfWork, $this->translator))->run(new ThreadPublishableInput((bool) $input->getOption('publish')), Reports::printer($input, $output))->status();
    }
}
