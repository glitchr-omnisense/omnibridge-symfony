<?php

namespace Omnibridge\Symfony;

use Omnibridge\Runtime;
use Omnibridge\Symfony\DependencyInjection\ContractsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The host's contracts (omniscient/omnibridge) answered by Symfony, for a
 * Symfony application that runs no omnibase: the adapters of
 * config/services.php bound under each contract's name, the locator of
 * ContractsPass, handed to Omnibridge\Runtime at boot - the counterpart of
 * omnibridge/laravel's OmnibridgeServiceProvider.
 *
 * An omnibase site does not register it: omnibase's BaseBundle loads the
 * same services (config/services.php, with omnibase's router), runs the same
 * pass with omnibase's own contracts added and seeds the registry itself.
 */
final class OmnibridgeBundle extends AbstractBundle
{
    /** The adapters' definitions, what an application's bundle imports to bind the contracts. */
    public const SERVICES = __DIR__.'/../config/services.php';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(self::SERVICES);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ContractsPass());
    }

    public function boot(): void
    {
        if ($this->container?->has(ContractsPass::SERVICE)) {
            Runtime::setContainer($this->container->get(ContractsPass::SERVICE));
        }
    }

    public function shutdown(): void
    {
        if (null !== $this->container && $this->container->has(ContractsPass::SERVICE)
            && Runtime::getContainer() === $this->container->get(ContractsPass::SERVICE)) {
            Runtime::setContainer(null);
        }
    }
}
