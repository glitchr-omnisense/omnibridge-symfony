<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\EntityDiscriminator;
use Base\Console\Task\EntityDiscriminatorInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\EntityDiscriminatorCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(EntityDiscriminator::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'entity:discriminator', aliases: [], description: '')]
    class EntityDiscriminatorCommand extends Legacy
    {
    }
} else {
    /** entity:discriminator: its options to omnibase's EntityDiscriminator task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'entity:discriminator', aliases: [], description: '')]
    class EntityDiscriminatorCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new EntityDiscriminator($this->unitOfWork))->run(new EntityDiscriminatorInput((string) $input->getArgument('entity')), Reports::printer($input, $output))->status();
        }
    }
}
