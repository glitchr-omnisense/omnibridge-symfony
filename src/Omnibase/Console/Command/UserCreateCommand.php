<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\UserCreate;
use Base\Console\Task\UserCreateInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\UserCreateCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(UserCreate::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'user:create', description: 'An account, its password to change at the first sign-in')]
    class UserCreateCommand extends Legacy
    {
    }
} else {
    /** user:create: its options to omnibase's UserCreate task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'user:create', description: 'An account, its password to change at the first sign-in')]
    class UserCreateCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new UserCreate($this->unitOfWork, $this->notifier))->run(new UserCreateInput(
                (string) $input->getArgument('username'),
                (string) $input->getArgument('email'),
                array_values((array) $input->getOption('role')),
                $input->getOption('password') ?: null,
                (bool) $input->getOption('keep-password'),
                (bool) $input->getOption('no-email'),
            ), Reports::printer($input, $output))->status();
        }
    }
}
