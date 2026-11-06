<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\NotifierMailTest;
use Base\Console\Task\NotifierMailTestInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\NotifierMailTestCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(NotifierMailTest::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(
    name: 'notifier:mail:test',
    description: 'Send a test email to either a specific user or to the technical recipient'
)]
    class NotifierMailTestCommand extends Legacy
    {
    }
} else {
    /** notifier:mail:test: its options to omnibase's NotifierMailTest task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(
    name: 'notifier:mail:test',
    description: 'Send a test email to either a specific user or to the technical recipient'
)]
    class NotifierMailTestCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new NotifierMailTest($this->notifier, $this->unitOfWork))->run(new NotifierMailTestInput($input->getOption('user') ?: null), Reports::printer($input, $output))->status();
        }
    }
}
