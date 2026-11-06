<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TranslationEntities;
use Base\Console\Task\TranslationEntitiesInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TranslationEntitiesCommand as Legacy;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TranslationEntities::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'translation:entities', aliases: [], description: '')]
    class TranslationEntitiesCommand extends Legacy
    {
    }
} else {
    /** translation:entities: its options to omnibase's TranslationEntities task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'translation:entities', aliases: [], description: '')]
    class TranslationEntitiesCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TranslationEntities($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
                ->run(new TranslationEntitiesInput('./src', $input->getOption('entity') ?? '', $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
                ->status();
        }
    }
}
