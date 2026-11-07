<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use App\Entity\User;
use Omnibridge\Symfony\Omnibase\Console\Command;
use App\Repository\UserRepository;
use Base\Notifier\NotifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notifier:mail:test',
    description: 'Send a test email to either a specific user or to the technical recipient'
)]
class NotifierMailTestCommand extends Command
{
    protected NotifierInterface $notifier;
    /** Doctrine's App\Repository\UserRepository on omnisense/omnibase 4.x; none on 5.x, whose task reads the accounts through the persistence chosen. */
    protected ?UserRepository $userRepository = null;

    public function __construct(...$args)
    {
        $this->notifier = first(extract_instanceof(NotifierInterface::class, ...$args));
        $this->userRepository = first(extract_instanceof(UserRepository::class, ...$args)) ?: null;
        parent::__construct(...$args);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                'User ID (or email, or username) to send the notification to'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->notifier->hasLoopback() && $this->notifier->getTechnicalRecipient()) {
            $io->note('Notifier loopback is enabled. Notifications will be sent to the technical recipient: '. PHP_EOL . $this->notifier->getTechnicalRecipient());
        }

        $userId = $input->getOption('user');        
        $user = $this->userRepository->findByIdOrEmailOrUsername($userId)?->getResult();
        if ($userId && !$user) {
            $io->warning("No user found by ID, email or username using `$userId`.");
        }

        $mail = mailparse($this->notifier->getTechnicalRecipient()->getEmail());
        if(!$user) {
            $user = $this->userRepository->findByEmail(first($mail))?->getResult();
            if (!$user) {
                $io->warning("No user found in database as technical recipient: " . $this->notifier->getTechnicalRecipient());
                $user = new User();
                // A username where the application's user has one (Base\Entity\User has none).
                if (\Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor()->isWritable($user, 'username')) {
                    \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor()->setValue($user, 'username', first($mail));
                }
                $user->setEmail(first(array_keys($mail)));
                $userId = 0;
            }
        }

        if(!$user) {
            
            $user = $this->userRepository->findOne();
            $userId = $user?->getId();
            if (!$user) {
                $io->warning("No user found in database as technical recipient: " . $this->notifier->getTechnicalRecipient());
                return Command::FAILURE;
            }
        }

        $notification = $this->notifier->testEmail($user);
        $notification->send();
        $name = \Base\Entity\User::nameOf($user);
        $io->success("Sent test email to {$user->getEmail()} (Name: {$name}, ID: {$userId}) using async transport.");

        return Command::SUCCESS;
    }
}