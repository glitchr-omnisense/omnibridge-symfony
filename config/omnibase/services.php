<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// Registrations are split per domain; defaults (private services) are
// re-declared in each file. Load order is alphabetical and irrelevant:
// every service id is defined exactly once across the set.
return static function (ContainerConfigurator $container): void {
    $container->import('services/*.php');
};
