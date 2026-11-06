<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\CacheClearSessions;
use Base\Console\Task\CacheClearSessionsInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\CacheClearSessionsCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(CacheClearSessions::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'cache:clear:sessions', aliases: [], description: '')]
    class CacheClearSessionsCommand extends Legacy
    {
    }
} else {
    /** cache:clear:sessions: Symfony's session folder (var/sessions) to omnibase's CacheClearSessions task (its plan, step F5e, slice 3). */
    #[AsCommand(name: 'cache:clear:sessions', aliases: [], description: '')]
    class CacheClearSessionsCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new CacheClearSessions())->run(new CacheClearSessionsInput($this->projectDir.'/var/sessions'), Reports::printer($input, $output))->status();
        }
    }
}
