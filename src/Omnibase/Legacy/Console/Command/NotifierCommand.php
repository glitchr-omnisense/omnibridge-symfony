<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use App\Notifier\Notifier;
use Base\Notifier\Abstract\BaseNotifier;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:notifier', aliases: [], description: 'This command gives an overview of the templated notifications')]
class NotifierCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reflClass = new ReflectionClass(Notifier::class);

        $output->section()->writeln("Available templated notifications:");
        foreach ($reflClass->getMethods() as $method) {
            if ($method->class == BaseNotifier::class) {
                continue;
            }

            $output->section()->writeln(" * <info>" . $method->name . "</info>");
        }

        return Command::SUCCESS;
    }
}
