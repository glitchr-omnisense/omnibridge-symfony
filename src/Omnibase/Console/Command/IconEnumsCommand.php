<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\IconEnums;
use Base\Console\Task\IconEnumsInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\IconEnumsCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(IconEnums::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'icon:enums', aliases: [], description: '')]
    class IconEnumsCommand extends Legacy
    {
    }
} else {
    /** icon:enums: its options to omnibase's IconEnums task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'icon:enums', aliases: [], description: '')]
    class IconEnumsCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new IconEnums())->run(new IconEnumsInput($this->parameterBag->get('kernel.project_dir').'/src', (string) $input->getOption('enum')), Reports::printer($input, $output))->status();
        }
    }
}
