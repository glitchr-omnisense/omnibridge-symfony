<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TranslationCrud;
use Base\Console\Task\TranslationCrudInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TranslationCrudCommand as Legacy;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TranslationCrud::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'translation:crud', aliases: [], description: '')]
    class TranslationCrudCommand extends Legacy
    {
    }
} else {
    /** translation:crud: its options to omnibase's TranslationCrud task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'translation:crud', aliases: [], description: '')]
    class TranslationCrudCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TranslationCrud($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
                ->run(new TranslationCrudInput('./src', $input->getOption('crud') ?? '', $input->getOption('action'), $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
                ->status();
        }
    }
}
