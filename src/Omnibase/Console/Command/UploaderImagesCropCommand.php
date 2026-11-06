<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\UploaderImagesCrop;
use Base\Console\Task\UploaderImagesCropInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\UploaderImagesCropCommand as Legacy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

if (!class_exists(UploaderImagesCrop::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'uploader:images:crop', aliases: [], description: '')]
    class UploaderImagesCropCommand extends Legacy
    {
    }
} else {
    /**
     * uploader:images:crop: its options - and, to renormalise, the resolution chosen and the confirmation, asked
     * here - to omnibase's UploaderImagesCrop task (its plan, step F5e, slice 3), with the media controller, the
     * Report printed.
     */
    #[AsCommand(name: 'uploader:images:crop', aliases: [], description: '')]
    class UploaderImagesCropCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $definition = null;
            if ($input->getOption('normalize')) {
                $definition = $this->getHelper('question')->ask($input, $output, new ChoiceQuestion('Please select a resolution class to be used for renormalization.', UploaderImagesCrop::definitions(), false));
                if (null === $definition) {
                    return self::FAILURE;
                }
                $definition = new $definition();
            }

            $task = new UploaderImagesCrop($this->repositories, $this->mediaService, $this->defaultFormats, $this->unitOfWork, $this->mediaController);

            return $task->run(new UploaderImagesCropInput(
                $this->parameterBag->get('kernel.project_dir').'/src',
                $input->getOption('entity'),
                $input->getOption('property'),
                $input->getOption('uuid'),
                (bool) $input->getOption('orphans'),
                (bool) $input->getOption('delete-orphans'),
                (bool) $input->getOption('warmup'),
                (int) $input->getOption('batch'),
                $input->getOption('format') ?: null,
                null !== $definition,
                $definition,
            ), Reports::printer($input, $output), fn (string $question): bool => (bool) $this->getHelper('question')->ask($input, $output, new ConfirmationQuestion($question, false)))->status();
        }
    }
}
