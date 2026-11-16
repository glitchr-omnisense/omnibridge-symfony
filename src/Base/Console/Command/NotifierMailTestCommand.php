<?php

namespace Base\Console\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Base\Console\Task\NotifierMailTest;
use Base\Console\Task\NotifierMailTestInput;
use Base\Notifier\NotifierInterface;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** notifier:mail:test: its options to omnibase's NotifierMailTest task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
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
        return (new NotifierMailTest($this->notifier, $this->unitOfWork))->run(new NotifierMailTestInput($input->getOption('user') ?: null), Reports::printer($input, $output))->status();
    }
}
