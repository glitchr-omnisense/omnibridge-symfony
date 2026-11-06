<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\EntitiesInput;
use Base\Console\Task\IconEntities;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\IconEntitiesCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(IconEntities::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'icon:entities', aliases: [], description: '')]
    class IconEntitiesCommand extends Legacy
    {
    }
} else {
    /** icon:entities: its options to omnibase's IconEntities task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'icon:entities', aliases: [], description: '')]
    class IconEntitiesCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new IconEntities($this->unitOfWork))->run(new EntitiesInput($this->parameterBag->get('kernel.project_dir').'/src', $input->getOption('entity') ?: null, $input->getOption('id') ?: null), Reports::printer($input, $output))->status();
        }
    }
}
