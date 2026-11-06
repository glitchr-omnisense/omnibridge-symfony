<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Console\Task\TranslationControllers;
use Base\Console\Task\TranslationControllersInput;
use Omnibridge\Symfony\Omnibase\Console\Reports;
use Omnibridge\Symfony\Omnibase\Legacy\Console\Command\TranslationControllersCommand as Legacy;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!class_exists(TranslationControllers::class)) {
    // omnisense/omnibase 4.x: the task is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    #[AsCommand(name: 'translation:controllers', aliases: [], description: '')]
    class TranslationControllersCommand extends Legacy
    {
    }
} else {
    /**
     * translation:controllers: the kernel's routes and the options to omnibase's TranslationControllers task (its
     * plan, step F5e, slice 3), the Report printed. (The former code read a router it was never given.)
     */
    #[AsCommand(name: 'translation:controllers', aliases: [], description: '')]
    class TranslationControllersCommand extends Legacy
    {
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
}
