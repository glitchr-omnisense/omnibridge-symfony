<?php

namespace Base\Console\Command;

use Base\Console\Task\TranslationEnums;
use Base\Console\Task\TranslationEnumsInput;
use Base\Service\Localizer;
use Base\Service\Translator;
use Exception;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** translation:enums: its options to omnibase's TranslationEnums task (its plan, step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'translation:enums', aliases: [], description: '')]
class TranslationEnumsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('enum', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific enum ?');
        $this->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', Translator::NOUN_SINGULAR);
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TranslationEnums($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
            ->run(new TranslationEnumsInput('./src', $input->getOption('enum') ?? '', $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
            ->status();
    }
}
