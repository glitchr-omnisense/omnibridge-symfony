<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\BaseBundle;

use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'icon:enums', aliases: [], description: '')]
class IconEnumsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('enum', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific enum ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = dirname((new ReflectionClass('Base\\BaseBundle'))->getFileName());
        $enumRestriction = $input->getOption('enum') ?? "";
        $enums = array_merge(
            BaseBundle::getInstance()->getAllClasses($baseLocation . "/Enum"),
            BaseBundle::getInstance()->getAllClasses("./src/Enum"),
        );

        if ($enums) {
            $output->section()->writeln("Enum list: " . $enumRestriction);
        }
        foreach ($enums as $enum) {
            if (!str_starts_with($enum, $enumRestriction)) {
                continue;
            }
            if (!is_subclass_of($enum, \Base\Database\Type\EnumType::class) || !method_exists($enum, '__iconizeStatic')) {
                continue; // a native PHP enum (CommentState...): no permitted values to list
            }
            try {
                $enum::getPermittedValues(false);
            } catch (\Exception) {
                continue; // a group of enums (SystemOfUnits\...), no value of its own
            }

            $output->section()->writeln(" * <info>" . $enum . "</info>");

            $iconize = $enum::__iconizeStatic();
            $permittedValues = $enum::getPermittedValues(false);

            $maxLength = 0;
            foreach ($permittedValues as $value) {
                $maxLength = max($maxLength, strlen($value));
            }

            foreach ($permittedValues as $value) {
                $space = str_repeat(" ", max($maxLength - strlen($value), 0));
                $icons = is_array($iconize[$value]) ? $iconize[$value] : [$iconize[$value]];
                $output->section()->writeln("\t<warning>" . $value . "</warning> " . $space . ": [" . implode(",", $icons ?? []) . "]");
            }
        }

        return Command::SUCCESS;
    }
}
