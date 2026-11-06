<?php

namespace Omnibridge\Symfony\Omnibase\Console;

use Base\Console\Commands;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/** omnibase's Commands (its plan "omnibase agnostic", step F5e, slice 3) on Symfony's console application: a command run by its name, not interactively, printing where the caller prints. */
final class ApplicationCommands implements Commands
{
    public function __construct(private readonly ?Application $application, private readonly OutputInterface $output)
    {
    }

    public function run(string $name, array $arguments = []): ?int
    {
        try {
            $command = $this->application?->find($name);
        } catch (CommandNotFoundException) {
            return null;
        }
        if (null === $command) {
            return null;
        }

        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return $command->run($input, $this->output);
    }
}
