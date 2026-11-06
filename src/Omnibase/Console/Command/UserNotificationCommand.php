<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\UserNotification;
use Base\Console\Task\UserNotificationInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\UserNotificationCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(UserNotification::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'user:notifications', aliases: [], description: 'Counts, lists and erases the notifications read')]
    class UserNotificationCommand extends Legacy
    {
    }
} else {
    /** user:notifications: its options to omnibase's UserNotification task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'user:notifications', aliases: [], description: 'Counts, lists and erases the notifications read')]
    class UserNotificationCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new UserNotification($this->unitOfWork, $this->parameterBag))->run(new UserNotificationInput(
                $input->getOption('user'),
                $input->getOption('expiry'),
                (bool) $input->getOption('show'),
                (bool) $input->getOption('clear'),
                (bool) $input->getOption('clear-all'),
            ), Reports::printer($input, $output))->status();
        }
    }
}
