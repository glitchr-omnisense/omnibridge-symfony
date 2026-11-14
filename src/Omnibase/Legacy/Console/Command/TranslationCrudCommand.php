<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\BaseBundle;
use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\Localizer;
use Base\Service\Translator;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        $baseLocation = \Omnibridge\Symfony\Omnibase\BaseBundle::getBundleDir().'/src';
        $crudRestriction = $input->getOption('crud') ?? "";
        $cruds = array_merge(
            BaseBundle::getInstance()->getAllClasses($baseLocation . "/Controller/Admin/Crud"),
            BaseBundle::getInstance()->getAllClasses("./src/Controller/Admin/Crud"),
        );

        $maxLength = 0;
        if (!$crudRestriction) {
            foreach ($cruds as $crud) {
                $maxLength = max(strlen($crud), $maxLength);
            }
        }

        $action = $input->getOption('action');

        $locale = $input->getOption('locale');
        $locale = $locale ? $this->localizer->getLocale($locale) : null;
        $availableLocales = Localizer::getAvailableLocales();
        if ($locale && !in_array($locale, $availableLocales)) {
            throw new Exception("Locale not found in the list of available locale: [" . implode(",", $availableLocales) . "]");
        }

        $suffix = $input->getOption('suffix');
        if ($cruds) {
            $output->section()->writeln("CRUD controller list: " . $crudRestriction);
        }
        foreach ($cruds as $crud) {
            if (!str_starts_with($crud, $crudRestriction)) {
                continue;
            }

            $trans = "";
            foreach ($availableLocales as $currentLocale) {
                if ($locale !== null && $locale != $currentLocale) {
                    continue;
                }
                if ($locale === null) {
                    $prefix = "\n\t - ";
                    $space = "";
                } else {
                    $prefix = "";
                    $space = str_repeat(" ", max($maxLength - strlen($crud), 0));
                }

                $translation = "";
                $path = explode("\\", $crud);
                $path = str_strip(implode(".", tail($path, 4)), "", "CrudController");

                $translationPath = "@" . Translator::DOMAIN_BACKEND . ".crud." . camel2snake($path) . "." . $action . "." . $suffix;
                $translationPathStr = $prefix . "@" . Translator::DOMAIN_BACKEND . "[$currentLocale].<ln>crud." . camel2snake($path) . "." . $action . "." . $suffix . "</ln>";
                $translation = $this->translator->trans($translationPath, [], null, $currentLocale);

                if ($translation == $translationPath) {
                    $trans .= "<warning>" . $translationPathStr . "</warning><red> = \"no translation found\"</red> (possible entity fallback)";
                } else {
                    $trans .= "<warning>" . $translationPathStr . " </warning>= \"" . $translation . "\"";
                }
            }

            $output->section()->writeln(" * <info>" . trim($crud) . "</info> " . $space . ": $trans");
        }


        return Command::SUCCESS;
    }
}
