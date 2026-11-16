<?php

namespace Base\Console\Command;

use Base\Console\Task\DemoReset;
use Base\Console\Task\DemoResetInput;
use Base\Database\UnitOfWorks;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoGuard;
use Base\Demo\DemoMode;
use Base\Exception\DemoRefusedException;
use Doctrine\Persistence\ManagerRegistry;
use Base\Console\ApplicationCommands;
use Base\Console\Reports;
use Base\Subscriber\SecondLevelCacheConsoleSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;

/**
 * demo:reset: omnibase's DemoReset task (its plan, step F5e, slice 3), run with this console's commands
 * (doctrine:fixtures:load, uploader:entities, cache:pool:clear) and Doctrine's second-level cache, the Report
 * printed.
 */
#[AsCommand(name: 'demo:reset', description: 'Reloads the demonstration: fixtures, uploaded files, caches (demo environment only)')]
class DemoResetCommand extends Command
{
    public const LOCK = 'base-demo-reset';

    /**
     * @param string[] $purge  directories emptied before the fixtures are loaded (base.demo.reset.purge)
     * @param string[] $groups the fixture groups loaded (base.demo.reset.groups); none: every fixture
     */
    public function __construct(
        protected readonly DemoMode $mode,
        protected readonly DemoGuard $guard,
        protected readonly DemoAccountFactory $accounts,
        protected readonly ?ManagerRegistry $doctrine = null,
        protected readonly ?LockFactory $lockFactory = null,
        protected readonly ?SecondLevelCacheConsoleSubscriber $secondLevelCache = null,
        protected readonly ?LoggerInterface $logger = null,
        #[Autowire('%base.demo.reset.purge%')] protected readonly array $purge = [],
        #[Autowire('%base.demo.reset.orphans%')] protected readonly bool $orphans = true,
        #[Autowire('%base.demo.reset.groups%')] protected readonly array $groups = [],
        protected readonly ?DemoAccountRegistry $registry = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep-files', null, InputOption::VALUE_NONE, 'Leave the uploaded files as they are (the database and the caches only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $task = new DemoReset(
            $this->mode,
            $this->guard,
            $this->accounts,
            null !== $this->doctrine ? fn () => UnitOfWorks::of($this->doctrine->getManager()) : null,
            $this->lockFactory,
            $this->logger,
            $this->registry,
            null !== $this->secondLevelCache ? $this->secondLevelCache->evict(...) : null,
        );

        return $task->run(new DemoResetInput((bool) $input->getOption('keep-files'), $this->purge, $this->orphans, $this->groups), new ApplicationCommands($this->getApplication(), $output), Reports::printer($input, $output))->status();
    }

    /** @param array<string, mixed> $arguments */
    private function call(string $command, array $arguments, OutputInterface $output): int
    {
        try {
            $found = $this->getApplication()?->find($command);
        } catch (CommandNotFoundException) {
            $found = null;
        }
        if (null === $found) {
            $this->say($output, sprintf('command "%s" not found.', $command), 'warning');

            return self::FAILURE;
        }

        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return $found->run($input, $output);
    }

    /** Everything inside a directory, the directory kept (it may be a mounted volume); the number of files removed. */
    private function emptyDirectory(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $files = iterator_count((new Finder())->in($directory)->files()->ignoreDotFiles(false)->ignoreVCS(false));
        $entries = iterator_to_array((new Finder())->in($directory)->depth(0)->ignoreDotFiles(false)->ignoreVCS(false), false);
        (new Filesystem())->remove($entries);

        return $files;
    }

    private function say(OutputInterface $output, string $message, string $level = 'info'): void
    {
        $output->writeln(sprintf('%s [demo:reset] %s', gmdate('Y-m-d\TH:i:s\Z'), $message));
        $this->logger?->log($level, 'demo:reset: '.$message);
    }
}
