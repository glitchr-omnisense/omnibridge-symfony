<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\CacheClear;
use Base\Console\Task\CacheClearInput;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataFactory;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\CacheClearCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

if (!class_exists(CacheClear::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'cache:clear', aliases: [], description: '')]
    class CacheClearCommand extends Legacy
    {
    }
} else {
    /**
     * cache:clear: omnibase's CacheClear task (its plan, step F5e, slice 3: the lock, the checks, the counter of
     * clears, the second clear) around FrameworkBundle's own cache:clear, the Report printed between what it prints.
     * The counter's static questions (getNClears(), isFirstClear()...) stay this class's, on the same file.
     */
    #[AsCommand(name: 'cache:clear', aliases: [], description: '')]
    class CacheClearCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $this->cacheClearCommand->setApplication($this->getApplication());

            return (new CacheClear($this->parameterBag, $this->flysystem, $this->notifier))->run(
                new CacheClearInput(
                    $this->projectDir,
                    $this->cacheDir,
                    checks: !$input->getOption('no-extension'),
                    optionalWarmers: !$input->getOption('no-optional-warmers') && !$input->getOption('no-warmup'),
                    sessions: (bool) $input->getOption('sessions'),
                    phpInfo: $this->router->isDebug(),
                    hostFallback: (bool) $this->router->getHostFallback(),
                    customMetadataFactory: $this->entityManager->getMetadataFactory() instanceof ClassMetadataFactory,
                ),
                fn (): int => $this->cacheClearCommand->execute($input, $output),
                fn () => (new Process(['php', 'bin/console', 'cache:clear']))->setWorkingDirectory($this->projectDir)->mustRun(),
                fn () => $this->cacheClearSessionsCommand->execute($input, $output),
                Reports::printer($input, $output),
            )->status();
        }
    }
}
