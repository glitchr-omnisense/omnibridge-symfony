<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\UploaderImages;
use Base\Console\Task\UploaderImagesInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\UploaderImagesCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(UploaderImages::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'uploader:images', aliases: [], description: '')]
    class UploaderImagesCommand extends Legacy
    {
    }
} else {
    /** uploader:images: its options to omnibase's UploaderImages task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'uploader:images', aliases: [], description: '')]
    class UploaderImagesCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new UploaderImages($this->repositories, $this->mediaService, $this->defaultFormats))->run(new UploaderImagesInput(
                $this->parameterBag->get('kernel.project_dir').'/src',
                $input->getOption('entity'),
                $input->getOption('property'),
                $input->getOption('uuid'),
                (bool) $input->getOption('orphans'),
                (bool) $input->getOption('delete-orphans'),
                (bool) $input->getOption('warmup'),
                (int) $input->getOption('batch'),
                $input->getOption('format') ?: null,
            ), Reports::printer($input, $output))->status();
        }
    }
}
