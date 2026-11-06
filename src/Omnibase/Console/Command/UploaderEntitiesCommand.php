<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\UploaderEntities;
use Base\Console\Task\UploaderEntitiesInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\UploaderEntitiesCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(UploaderEntities::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'uploader:entities', aliases: [], description: '')]
    class UploaderEntitiesCommand extends Legacy
    {
    }
} else {
    /** uploader:entities: its options to omnibase's UploaderEntities task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'uploader:entities', aliases: [], description: '')]
    class UploaderEntitiesCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new UploaderEntities($this->repositories))->run(new UploaderEntitiesInput(
                $this->parameterBag->get('kernel.project_dir').'/src',
                $input->getOption('entity'),
                $input->getOption('property'),
                $input->getOption('uuid'),
                (bool) $input->getOption('orphans'),
                (bool) $input->getOption('delete-orphans'),
            ), Reports::printer($input, $output))->status();
        }
    }
}
