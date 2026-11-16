<?php

namespace Base\Console\Command;

use Base\Console\Task\TranslationCrud;
use Base\Console\Task\TranslationCrudInput;
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

/** translation:crud: its options to omnibase's TranslationCrud task (its plan, step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'translation:crud', aliases: [], description: '')]
class TranslationCrudCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('crud', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific CRUD controller ?');
        $this->addOption('action', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific CRUD action ?', "index");
        $this->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', "title");
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TranslationCrud($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
            ->run(new TranslationCrudInput('./src', $input->getOption('crud') ?? '', $input->getOption('action'), $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
            ->status();
    }
}
