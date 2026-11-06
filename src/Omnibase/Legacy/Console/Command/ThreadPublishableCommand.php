<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;


use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\RowLocks;
use Base\Enum\ThreadState;
use Base\Repository\Threads;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        $actionPublish = $input->getOption('publish');

        $threads = $this->threads->byStateUncached(ThreadState::FUTURE);

        $publishableThreads = array_filter(
            $threads,
            function ($thread) use ($actionPublish) {
                if (!$thread->isPublishable()) {
                    return false;
                }

                if ($actionPublish) {
                    // Its row locked and read again: two runs at once (the
                    // cron, an admin's edit) both published it, and its
                    // authors and mentioned members were mailed twice.
                    Backends::of($this->unitOfWork, RowLocks::class)->locked($thread, function () use ($thread): void {
                        $this->unitOfWork->refresh($thread);
                        if ($thread->isPublishable()) {
                            $thread->poke();
                            $this->unitOfWork->flush();
                        }
                    });
                }

                return true;
            }
        );


        // Show future article list
        $nThreads = count($threads);
        $nPublishableThreads = count($publishableThreads);

        if ($nThreads) {
            $output->section()->writeln("", OutputInterface::VERBOSITY_VERBOSE);
        }
        foreach ($threads as $key => $thread) {
            $publishableStr = $thread->isPublishable() ? "<info,bkg>[O]</info,bkg>" : "[X]";
            $message = $publishableStr . " <info>Entry ID #" . ($key + 1) . "</info>: <ln>" . $this->translator->transEntity($thread) . " #" . $thread->getId() . " \"" . $thread->getTitle() . "\"</ln>";
            if (($parent = $thread->getParent())) {
                $message .= " in <ln>" . $this->translator->transEntity($parent) . " #" . $parent->getId() . " " . $parent->getTitle() . " </ln>";
            }

            $message .= " -- Publishable in \"" . $thread->getPublishTimeStr() . "\"";

            $output->section()->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
        }

        if ($actionPublish && $nPublishableThreads) {
            $msg = ' [OK] ' . $nThreads . ' scheduled thread(s) found: ' . $nPublishableThreads . ' thread(s) publishable => These are now published. ';
            $output->writeln('');
            $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
            $output->writeln('');
        } elseif ($nPublishableThreads) {
            $msg = ' [WARN] ' . $nThreads . ' scheduled thread(s) found: ' . $nPublishableThreads . ' thread(s) publishable, please confirm using `--publish` option. ';
            $output->writeln('');
            $output->writeln('<warning,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</warning,bkg>');
            $output->writeln('');
        } else {
            $msg = ' [OK] ' . $nThreads . ' scheduled thread(s) found: ' . $nPublishableThreads . ' thread(s) publishable. ';
            $output->writeln('');
            $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
            $output->writeln('');
        }

        return Command::SUCCESS;
        return Command::SUCCESS;
    }
}
