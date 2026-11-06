<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\MailerTrap;
use Base\Console\Task\MailerTrapInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\MailTrapCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(MailerTrap::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'base:mailer:trap', description: 'Hold, inspect, purge or release outgoing mail')]
    class MailTrapCommand extends Legacy
    {
    }
} else {
    /** base:mailer:trap: its options to omnibase's MailerTrap task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'base:mailer:trap', description: 'Hold, inspect, purge or release outgoing mail')]
    class MailTrapCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            // mailer.transports, not the mailer: a release through the mailer would be handed back to an armed trap.
            return (new MailerTrap($this->trap))->run(
                new MailerTrapInput((string) $input->getArgument('action'), $input->getArgument('id'), (bool) $input->getOption('all'), $input->getOption('reason')),
                Reports::printer($input, $output),
                fn ($message, $envelope) => $this->transport->send($message, $envelope),
            )->status();
        }
    }
}
