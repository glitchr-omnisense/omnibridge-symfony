<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\TrashManager;
use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Empties the trash of everything past its grace period
 * (`base.extension.empty_trash`, "+7 days" by default).
 *
 * Meant for cron. Defaults to a dry run: this is the only thing in the stack
 * that destroys content beyond recovery, so it says what it would do unless
 * told --force.
 */
#[AsCommand(name: 'trash:purge', aliases: [], description: 'Destroy trashed entities past their grace period')]
class TrashPurgeCommand extends Command
{
    protected TrashManager $trashManager;

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually destroy them (without this, only reports)');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Destroy at most N entries in this run');
    }

    public function setTrashManager(TrashManager $trashManager): self
    {
        $this->trashManager = $trashManager;
        return $this;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        $now = new DateTime();
        $expired = $this->trashManager->expired($now, $limit);

        if (empty($expired)) {
            $output->writeln("<info>Nothing to purge.</info> The trash holds " . $this->trashManager->count() . " entr" . ($this->trashManager->count() === 1 ? "y" : "ies") . ", none past its grace period.");
            return self::SUCCESS;
        }

        foreach ($expired as $trashBall) {
            $label = $trashBall->getEntityData()["label"] ?? null;
            $output->writeln(sprintf(
                "  %s <info>%s</info> #%s%s <ln>(expired %s)</ln>",
                $force ? "destroying" : "would destroy",
                $trashBall->getEntityClass(),
                $trashBall->getEntityId(),
                $label ? " \"" . $label . "\"" : "",
                $trashBall->getPermanentAfter()?->format("Y-m-d H:i") ?? "?"
            ));
        }

        if (!$force) {
            $output->writeln("");
            $output->writeln("<warning>Dry run.</warning> " . count($expired) . " entr" . (count($expired) === 1 ? "y" : "ies") . " would be destroyed. Pass --force to do it.");
            return self::SUCCESS;
        }

        $destroyed = $this->trashManager->purge($now, $limit);
        $output->writeln("");
        $output->writeln("<info>Purged.</info> " . $destroyed . " entit" . ($destroyed === 1 ? "y" : "ies") . " destroyed.");

        return self::SUCCESS;
    }
}
