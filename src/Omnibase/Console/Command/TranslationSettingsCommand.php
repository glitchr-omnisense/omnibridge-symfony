<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TranslationSettings;
use Base\Console\Task\TranslationSettingsInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TranslationSettingsCommand as Legacy;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TranslationSettings::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'translation:settings', aliases: [], description: '')]
    class TranslationSettingsCommand extends Legacy
    {
    }
} else {
    /** translation:settings: its options to omnibase's TranslationSettings task (its plan, step F5e, slice 3), the Report printed. */
    #[AsCommand(name: 'translation:settings', aliases: [], description: '')]
    class TranslationSettingsCommand extends Legacy
    {
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            return (new TranslationSettings($this->translator, $this->localizer, new LocalizerLocales($this->localizer), $this->settingBag))
                ->run(new TranslationSettingsInput($input->getOption('path'), (bool) $input->getOption('raw'), $input->getOption('locale')), Reports::printer($input, $output))
                ->status();
        }
    }
}
