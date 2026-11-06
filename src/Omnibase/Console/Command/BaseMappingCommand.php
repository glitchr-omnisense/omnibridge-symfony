<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\BaseMapping;
use Base\Console\Task\BaseMappingInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\BaseMappingCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(BaseMapping::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'base:mapping', aliases: [], description: 'This command gives access to the mapping applied from \\Base to \\App namespace

    This is meant to avoid rewriting Base classes
    and use customized \\App classes extending from Base classes')]
    class BaseMappingCommand extends Legacy
    {
    }
} else {
    /** base:mapping: its options to omnibase's BaseMapping task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'base:mapping', aliases: [], description: 'This command gives access to the mapping applied from \\Base to \\App namespace

    This is meant to avoid rewriting Base classes
    and use customized \\App classes extending from Base classes')]
    class BaseMappingCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new BaseMapping())->run(new BaseMappingInput($this->parameterBag->get('kernel.project_dir').'/src'), Reports::printer($input, $output))->status();
        }
    }
}
