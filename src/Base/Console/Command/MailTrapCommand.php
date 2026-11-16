<?php

namespace Base\Console\Command;

use Base\Console\Task\MailerTrap;
use Base\Console\Task\MailerTrapInput;
use Base\Mailer\MailTrap;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** base:mailer:trap: its options to omnibase's MailerTrap task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'base:mailer:trap', description: 'Hold, inspect, purge or release outgoing mail')]
class MailTrapCommand extends Command
{
    protected MailTrap $trap;
    protected TransportInterface $transport;

    public function setMailTrap(MailTrap $trap): static
    {
        $this->trap = $trap;
        return $this;
    }

    public function setMailerTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;
        return $this;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'status, on, off, list, show, purge, release', 'status')
            ->addArgument('id', InputArgument::OPTIONAL, 'Which held message, for show/purge/release')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Apply to every held message')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Note why the trap was armed, for whoever finds it later');
    }

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
