<?php

namespace Base\Console\Command;

use Base\Console\Task\TranslationControllers;
use Base\Console\Task\TranslationControllersInput;
use Base\Service\Localizer;
use Exception;
use Base\Console\Command;
use Base\Console\Reports;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * translation:controllers: the kernel's routes and the options to omnibase's TranslationControllers task (its
 * plan, step F5e, slice 3), the Report printed. (The former code read a router it was never given.)
 */
#[AsCommand(name: 'translation:controllers', aliases: [], description: '')]
class TranslationControllersCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('controller', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific controller ?');
        $this->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', "title");
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = [];
        foreach ($this->getApplication()->getKernel()->getContainer()->get('router')->getRouteCollection()->all() as $name => $route) {
            if (\is_string($controller = $route->getDefault('_controller'))) {
                $routes[$name] = $controller;
            }
        }

        return (new TranslationControllers($this->translator, $this->localizer, new LocalizerLocales($this->localizer)))
            ->run(new TranslationControllersInput($routes, $input->getOption('controller') ?? '', $input->getOption('suffix'), $input->getOption('locale')), Reports::printer($input, $output))
            ->status();
    }
}
