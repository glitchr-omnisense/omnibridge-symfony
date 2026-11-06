<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TranslationEnums;
use Base\Console\Task\TranslationEnumsInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TranslationEnumsCommand as Legacy;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TranslationEnums::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'translation:enums', aliases: [], description: '')]
    class TranslationEnumsCommand extends Legacy
    {
    }
} else {
    /** translation:enums: its options to omnibase's TranslationEnums task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'translation:enums', aliases: [], description: '')]
    class TranslationEnumsCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TranslationEnums($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
                ->run(new TranslationEnumsInput('./src', $input->getOption('enum') ?? '', $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
                ->status();
        }
    }
}
