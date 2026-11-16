<?php

namespace Base\Console\Command;

use Base\Console\Task\TranslationSettings;
use Base\Console\Task\TranslationSettingsInput;
use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Exception;
use Base\Console\Command;
use Base\Console\Reports;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** translation:settings: its options to omnibase's TranslationSettings task (its plan, step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'translation:settings', aliases: [], description: '')]
class TranslationSettingsCommand extends Command
{
    /**
     * @var SettingBagInterface
     */
    protected $settingBag;

    public function __construct(
        LocalizerInterface     $localizer,
        TranslatorInterface    $translator,
        ?UnitOfWork $unitOfWork,
        ParameterBagInterface  $parameterBag,
        SettingBagInterface    $settingBag
    )
    {
        parent::__construct($localizer, $translator, $unitOfWork, $parameterBag);
        $this->settingBag = $settingBag;
    }

    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific setting path ?');
        $this->addOption('raw', null, InputOption::VALUE_NONE, 'Should I display the raw information ? (including user label and help information)');
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TranslationSettings($this->translator, $this->localizer, new LocalizerLocales($this->localizer), $this->settingBag))
            ->run(new TranslationSettingsInput($input->getOption('path'), (bool) $input->getOption('raw'), $input->getOption('locale')), Reports::printer($input, $output))
            ->status();
    }
}
