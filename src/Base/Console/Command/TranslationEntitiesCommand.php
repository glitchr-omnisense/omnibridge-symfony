<?php

namespace Base\Console\Command;

use Base\Console\Task\TranslationEntities;
use Base\Console\Task\TranslationEntitiesInput;
use Base\Service\Localizer;
use Base\Service\Translator;
use Exception;
use Base\Database\Mapping\NamingStrategy;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** translation:entities: its options to omnibase's TranslationEntities task (its plan, step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'translation:entities', aliases: [], description: '')]
class TranslationEntitiesCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
        $this->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', Translator::NOUN_SINGULAR);
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TranslationEntities($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
            ->run(new TranslationEntitiesInput('./src', $input->getOption('entity') ?? '', $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
            ->status();
    }
}
