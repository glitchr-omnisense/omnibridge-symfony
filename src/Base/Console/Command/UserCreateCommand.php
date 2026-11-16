<?php

namespace Base\Console\Command;

use App\Entity\User;
use Base\Console\Task\UserCreate;
use Base\Console\Task\UserCreateInput;
use Base\Database\UnitOfWorks;
use Base\Entity\User\Token;
use Base\Enum\UserRole;
use Base\Notifier\NotifierInterface;
use Omnibridge\Persistence\UnitOfWork;
use Base\Console\Reports;
use Base\Subscriber\PasswordChangeSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** user:create: its options to omnibase's UserCreate task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'user:create', description: 'An account, its password to change at the first sign-in')]
class UserCreateCommand extends Command
{
    protected readonly UnitOfWork $unitOfWork;

    public function __construct(
        UnitOfWork $unitOfWork,
        protected readonly NotifierInterface $notifier,
    ) {
        $this->unitOfWork = $unitOfWork;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Its username')
            ->addArgument('email', InputArgument::REQUIRED, 'Its e-mail address')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Its role(s): USER, ADMIN, SUPERADMIN...', ['USER'])
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Its password (otherwise generated)')
            ->addOption('keep-password', null, InputOption::VALUE_NONE, 'Do not ask for a new password at the first sign-in')
            ->addOption('no-email', null, InputOption::VALUE_NONE, 'Do not e-mail the account its username and password');
    }

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
