<?php

namespace Base\Console\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Console\Task\UserNotification;
use Base\Console\Task\UserNotificationInput;
use Base\Database\UnitOfWorks;
use Base\Entity\User\Notification;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** user:notifications: its options to omnibase's UserNotification task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'user:notifications', aliases: [], description: 'Counts, lists and erases the notifications read')]
class UserNotificationCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'The account (its identifier); every account without it');
        $this->addOption('expiry', null, InputOption::VALUE_REQUIRED, 'The age past which a read notification is erased ("+30 days"); base.user.notifications.expiry without it');

        $this->addOption('show', null, InputOption::VALUE_NONE, 'List them');
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Erase the read ones older than the expiry');
        $this->addOption('clear-all', null, InputOption::VALUE_NONE, 'Erase every read one, whatever its age');
    }

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
