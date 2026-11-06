<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Database\UnitOfWorks;
use Base\Entity\User\Notification;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * The notifications kept in the database: counted (an account's with --user, everyone's
 * without), listed with --show, erased with --clear when read and older than
 * base.user.notifications.expiry (or --expiry), with --clear-all when read, whatever their age.
 * An unread notification is never erased.
 */
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
        $userIdentifier = $input->getOption('user');
        $actionClear = $input->getOption('clear');
        $actionShow = $input->getOption('show');
        $actionClearAll = $input->getOption('clear-all');

        $notificationRepository = UnitOfWorks::repositories($this->unitOfWork)->of(Notification::class);
        $userRepository = UnitOfWorks::repositories($this->unitOfWork)->of(User::class);
        if (!$userRepository instanceof UserRepository) {
            throw new \LogicException(sprintf('The repository of %s is not a %s.', User::class, UserRepository::class));
        }

        $notificationExpiry = $input->getOption('expiry') ?? $this->parameterBag->get("base.user.notifications.expiry");

        // Format monitored entries
        // Every account's without --user (loadUserByIdentifier() throws on an identifier nobody has).
        $user = null !== $userIdentifier ? $userRepository->loadUserByIdentifier($userIdentifier) : null;
        if ($user) {
            $notifications = $notificationRepository->findByUser($user)->getResult();
            $notifications_toErase = $notificationRepository->findByUserAndIsReadAndSentAtOlderThan($user, true, $notificationExpiry)->getResult();
            $notifications_isRead = $notificationRepository->findByUserAndIsRead($user, true)->getResult();
        } else {
            $notifications = $notificationRepository->findAll();
            $notifications_toErase = $notificationRepository->findByIsReadAndSentAtOlderThan(true, $notificationExpiry)->getResult();
            $notifications_isRead = $notificationRepository->findByIsRead(true)->getResult();
        }

        // Show notification list
        $nbNotifications = count($notifications);
        if ($actionClearAll) {
            $notifications_toErase = $notifications_isRead;
        }
        $nbNotifications_toErase = count($notifications_toErase);
        $nbNotifications_isRead = count($notifications_isRead);

        if ($actionShow) {
            foreach ($notifications as $key => $notification) {
                $message = "<info>Entry ID #" . ($key + 1) . "</info> / <red>User \"" . $notification->getUser() . "\"</red> / <ln>Notifications #" . $notification->getId() . "</ln> : " . str_shorten($notification->getContent(), 50);

                if (in_array($notification, $notifications_toErase)) {
                    $message .= "<warning><<-- READY TO ERASE</warning>";
                }
                $output->writeln($message);
            }
        }
        $output->writeln("<info>" . $nbNotifications . " notification(s)</info> found, <warning>" . $nbNotifications_isRead . " notification(s)</warning> are marked as read, <red>" . $nbNotifications_toErase . " notification(s)</red> older than " . $notificationExpiry);

        if (($actionClear || $actionClearAll) && $nbNotifications_toErase) {
            $output->writeln('<warning>These notifications are now erased..</warning>');

            // The expired read ones only - it erased every notification,
            // unread included - and one flush for all.
            foreach ($notifications_toErase as $notification) {
                $this->unitOfWork->remove($notification);
            }
            $this->unitOfWork->flush();
        }

        return Command::SUCCESS;
    }
}
