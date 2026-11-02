<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $parameterBag,
        SettingBagInterface    $settingBag
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
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
        $raw = $input->getOption('raw');
        $path = $input->getOption('path');

        $locale = $input->getOption('locale');
        $locale = $locale ? $this->localizer->getLocale($locale) : null;
        $availableLocales = Localizer::getAvailableLocales();
        if ($locale && !in_array($locale, $availableLocales)) {
            throw new Exception("Locale not found in the list of available locale: [" . implode(",", $availableLocales) . "]");
        }

        $rawSettings = array_transforms(
            fn($k, $v): array => $path ? [$k ? $path . "." . $k : $path, $v] : [$k, $v],
            $this->settingBag->denormalize($this->settingBag->getRaw($path))
        );

        if (!$rawSettings) {
            throw new Exception("No settings found for \"$path\"");
        }
        $settings = array_map_recursive(
            fn($s) => array_transforms(
                fn($i, $k): array => [$k, null],
                $s->getTranslations()->getKeys()
            ),
            $rawSettings
        );

        foreach ($settings as $path => $_) {
            foreach ($_ as $currentLocale => $array) {
                if ($locale !== null && $locale != $currentLocale) {
                    continue;
                }
                $settings[$path][$currentLocale] = $rawSettings[$path]->translate($currentLocale)->getValue();
            }
        }

        $maxLength = 0;
        foreach ($settings as $path => $setting) {
            if ($raw) {
                $additionalLength = strlen(".label");
            } else {
                $additionalLength = strlen((count($setting) == 1) ? "[single,]" : "");
            }
            $maxLength = max(strlen($path ?? 0) + $additionalLength + 1, $maxLength ?? 0);
        }

        if ($settings) {
            $output->section()->writeln("Setting list: " . $path);
        }

        foreach ($settings as $path => $setting) {
            $singleLocale = (count($setting) == 1);
            if ($raw) {
                $additionalLength = strlen(".label");
            } else {
                $additionalLength = strlen((count($setting) == 1) ? "[single,]" : "");
            }

            $space = str_repeat(" ", max($maxLength - strlen($path) - $additionalLength, 0));

            $singleLocale = (count($setting) == 1);
            if ($raw) {
                $rawSetting = $rawSettings[$path];
                foreach ($availableLocales as $currentLocale) {
                    $value = $rawSetting->translate($currentLocale)->getLabel() ?? "<red>* no entry found *</red>";
                    $output->section()->writeln(" * <info>" . trim($path) . ".label" . $space . "<magenta>[$currentLocale]</magenta></info> : $value");
                }

                foreach ($availableLocales as $currentLocale) {
                    $value = $rawSetting->translate($currentLocale)->getHelp() ?? "<red>* no entry found *</red>";
                    $output->section()->writeln(" * <info>" . trim($path) . ".help" . $space . " <magenta>[$currentLocale]</magenta></info> : $value");
                }
                $output->section()->writeln("");
            } else {
                foreach ($availableLocales as $currentLocale) {
                    if ($singleLocale && !array_key_exists($currentLocale, $setting)) {
                        continue;
                    }

                    $value = $setting[$currentLocale] ?? "<red>* no entry found *</red>";
                    if (is_array($value)) {
                        $value = "\n   {\n\t" . implode(",\n\t", $value) . "\n   }";
                    }
                    if (!is_stringeable($value)) {
                        $value = get_class($value) . "([...])";
                    }

                    $output->section()->writeln(" * <info>" . trim($path) . $space . "<magenta>[" . ($singleLocale ? "single," : "") . "$currentLocale]</magenta></info> : <warning>$value</warning>");
                }
            }
        }

        return Command::SUCCESS;
    }
}
