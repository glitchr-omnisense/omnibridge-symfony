<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Base\BaseBundle;
use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\Localizer;
use Base\Service\Translator;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        $baseLocation = \Base\BaseBundle::getBundleDir().'/src';
        $enumRestriction = $input->getOption('enum') ?? "";
        $enums = array_merge(
            BaseBundle::getInstance()->getAllClasses("./src/Enum"),
            BaseBundle::getInstance()->getAllClasses($baseLocation . "/Enum"),
        );

        $maxLength = 0;
        if (!$enumRestriction) {
            foreach ($enums as $enum) {
                $maxLength = max(strlen($enum), $maxLength);
            }
        }

        $locale = $input->getOption('locale');
        $locale = $locale ? $this->localizer->getLocale($locale) : null;
        $availableLocales = Localizer::getAvailableLocales();
        if ($locale && !in_array($locale, $availableLocales)) {
            throw new Exception("Locale not found in the list of available locale: [" . implode(",", $availableLocales) . "]");
        }

        $suffix = $input->getOption('suffix');
        if ($enums) {
            $output->section()->writeln("Enum list: " . $enumRestriction);
        }
        foreach ($enums as $enum) {
            if (!str_starts_with($enum, $enumRestriction)) {
                continue;
            }
            if (!is_subclass_of($enum, \Base\Database\Type\EnumType::class)) {
                continue; // a native PHP enum (CommentState...): no permitted values to list
            }
            try {
                $enum::getPermittedValues(false);
            } catch (\Exception) {
                continue; // a group of enums (SystemOfUnits\...), no value of its own
            }

            $space = "";
            $trans = "";

            $path = explode("\\", $enum);

            foreach ($availableLocales as $currentLocale) {
                if ($locale !== null && $locale != $currentLocale) {
                    continue;
                }
                if ($locale === null) {
                    $prefix = "\n\t - ";
                    $space = "";
                } else {
                    $prefix = "";
                    $space = str_repeat(" ", max($maxLength - strlen($enum), 0));
                }

                $path = explode("\\", $enum);
                $path = implode(".", tail($path, 2));
                $translationPath = "@enums." . camel2snake($path) . "." . $suffix;
                $translationPathStr = $prefix . "@enums[$currentLocale].<ln>" . camel2snake($path) . "." . $suffix . "</ln>";
                $translation = $this->translator->trans($translationPath, [], null, $currentLocale);

                if ($translation == $translationPath) {
                    $trans .= "<warning>" . $translationPathStr . "</warning><red> = \"no translation found\"</red>";
                } else {
                    $trans .= "<warning>" . $translationPathStr . " </warning>= \"" . $translation . "\"";
                }
            }

            $output->section()->writeln("\n * <magenta>" . trim($enum) . "</magenta> " . $space . ": $trans");

            $maxValueLength = 0;
            foreach ($enum::getPermittedValues(false) as $value) {
                $maxValueLength = max(strlen($enum . "::" . $value), $maxValueLength);
            }

            foreach ($enum::getPermittedValues(false) as $value) {

                $value = strval($value);
                $translatedValue = "";
                foreach ($availableLocales as $currentLocale) {

                    if ($locale !== null && $locale != $currentLocale) {
                        continue;
                    }
                    if ($locale === null) {
                        $prefix = "\n\t\t - ";
                        $space = "";
                    } else {
                        $prefix = "";
                        $space = str_repeat(" ", max($maxValueLength - strlen($enum . "::" . $value), 0));
                    }

                    $translationPath = "@enums." . camel2snake($path) . "." . strtolower($value) . "." . $suffix;
                    $translationPathStr = $prefix . "@enums[$currentLocale].<ln>" . camel2snake($path) . "." . strtolower($value) . "." . $suffix . "</ln>";
                    $translation = $this->translator->trans($translationPath, [], null, $currentLocale);

                    if ($translation == $translationPath) {
                        $translatedValue .= "<warning>" . $translationPathStr . "</warning><red> = \"no translation found\"</red>";
                    } else {
                        $translatedValue .= "<warning>" . $translationPathStr . " </warning>= \"" . $translation . "\"";
                    }
                }
                $output->section()->writeln("\t * <info>" . trim($enum . "::" . $value) . "</info> " . $space . ": $translatedValue");
            }
        }


        return Command::SUCCESS;
    }
}
