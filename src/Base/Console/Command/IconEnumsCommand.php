<?php

namespace Base\Console\Command;

use Base\Console\Task\IconEnums;
use Base\Console\Task\IconEnumsInput;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** icon:enums: its options to omnibase's IconEnums task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'icon:enums', aliases: [], description: '')]
class IconEnumsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('enum', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific enum ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new IconEnums())->run(new IconEnumsInput($this->parameterBag->get('kernel.project_dir').'/src', (string) $input->getOption('enum')), Reports::printer($input, $output))->status();
    }
}
