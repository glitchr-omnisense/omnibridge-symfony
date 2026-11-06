<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\ThreadPublishable;
use Base\Console\Task\ThreadPublishableInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\ThreadPublishableCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(ThreadPublishable::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'thread:publishable', aliases: [], description: '')]
    class ThreadPublishableCommand extends Legacy
    {
    }
} else {
    /** thread:publishable: its options to omnibase's ThreadPublishable task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'thread:publishable', aliases: [], description: '')]
    class ThreadPublishableCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new ThreadPublishable($this->threads, $this->unitOfWork, $this->translator))->run(new ThreadPublishableInput((bool) $input->getOption('publish')), Reports::printer($input, $output))->status();
        }
    }
}
