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

    private function status(SymfonyStyle $io): int
    {
        $held = \count($this->trap->all());

        if ($details = $this->trap->armedDetails()) {
            $io->warning(\sprintf('Trap ARMED since %s%s - no mail is leaving this application.',
                $details['since'], $details['reason'] ? ' (' . $details['reason'] . ')' : ''));
        } else {
            $io->success('Trap off - mail is being delivered normally.');
        }

        $io->writeln(\sprintf(' Held messages: <info>%d</info>', $held));
        $io->writeln(\sprintf(' Directory:     %s', $this->trap->getDirectory()));

        // Held mail outlives the trap on purpose: disarming should restore
        // delivery without also deciding, silently, what happens to the
        // messages caught while it was on.
        if (0 === $held || $this->trap->isArmed()) {
            return self::SUCCESS;
        }

        $io->note('Messages are still held from an earlier session. Release or purge them - the trap being off does not send them.');

        return self::SUCCESS;
    }

    private function arm(SymfonyStyle $io, ?string $reason): int
    {
        $this->trap->arm($reason);
        $io->warning('Trap ARMED. Nothing will be delivered until "base:mailer:trap off".');
        $io->writeln(' Already-queued mail is caught too: the trap sits at send time, not at queue time.');

        return self::SUCCESS;
    }

    private function disarm(SymfonyStyle $io): int
    {
        $this->trap->disarm();
        $io->success('Trap off - mail is being delivered again.');

        if ($held = \count($this->trap->all())) {
            $io->note(\sprintf('%d message(s) are still held. They are NOT sent by disarming - use "release" or "purge".', $held));
        }

        return self::SUCCESS;
    }

    private function list(SymfonyStyle $io): int
    {
        $rows = $this->trap->all();
        if ([] === $rows) {
            $io->success('Nothing held.');

            return self::SUCCESS;
        }

        $io->table(['id', 'captured', 'to', 'subject'], \array_map(static fn (array $r) => [
            $r['id'] ?? '?',
            $r['capturedAt'] ?? '?',
            \implode(', ', $r['to'] ?? []),
            $r['subject'] ?? '(no subject)',
        ], $rows));

        return self::SUCCESS;
    }

    private function show(SymfonyStyle $io, ?string $id): int
    {
        if (null === $id) {
            $io->error('Which one? Pass an id from "base:mailer:trap list".');

            return self::FAILURE;
        }

        $held = $this->trap->read($id);
        if (null === $held) {
            $io->error(\sprintf('No held message "%s".', $id));

            return self::FAILURE;
        }

        [$message] = $held;
        $io->writeln($message->toString());

        return self::SUCCESS;
    }

    private function purge(SymfonyStyle $io, ?string $id, bool $all): int
    {
        [$targets, $error] = $this->targets($io, $id, $all);
        if (null !== $error) {
            return $error;
        }

        $done = 0;
        foreach ($targets as $target) {
            $done += $this->trap->forget($target) ? 1 : 0;
        }

        $io->success(\sprintf('%d message(s) discarded. They were never sent.', $done));

        return self::SUCCESS;
    }

    private function release(SymfonyStyle $io, ?string $id, bool $all): int
    {
        [$targets, $error] = $this->targets($io, $id, $all);
        if (null !== $error) {
            return $error;
        }

        if ($this->trap->isArmed()) {
            // Sending on the transport does NOT skip the trap - the transport
            // dispatches MessageEvent as well, so an armed trap caught the
            // release and re-captured it under a new id while this command
            // reported success. bypass() below is what actually gets it out.
            $io->warning('The trap is still armed - these are being sent anyway, deliberately.');
        }

        $sent = 0;
        foreach ($targets as $target) {
            $held = $this->trap->read($target);
            if (null === $held) {
                $io->error(\sprintf('No held message "%s".', $target));

                continue;
            }

            [$message, $envelope] = $held;
            $this->trap->bypass(fn () => $this->transport->send($message, $envelope));
            $this->trap->forget($target);
            ++$sent;
        }

        $io->success(\sprintf('%d message(s) sent.', $sent));

        return self::SUCCESS;
    }

    /**
     * @return array{0: list<string>, 1: ?int}
     */
    private function targets(SymfonyStyle $io, ?string $id, bool $all): array
    {
        if ($all) {
            return [\array_map(static fn (array $r) => (string) ($r['id'] ?? ''), $this->trap->all()), null];
        }

        if (null === $id) {
            $io->error('Pass an id from "base:mailer:trap list", or --all.');

            return [[], self::FAILURE];
        }

        return [[$id], null];
    }

    private function unknown(SymfonyStyle $io, string $action): int
    {
        $io->error(\sprintf('Unknown action "%s". Try: status, on, off, list, show, purge, release.', $action));

        return self::FAILURE;
    }
}
