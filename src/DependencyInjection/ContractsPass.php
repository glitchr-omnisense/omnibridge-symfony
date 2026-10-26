<?php

namespace Omnibridge\Symfony\DependencyInjection;

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Files;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The locator Omnibridge\Runtime reaches the host's contracts through under
 * Symfony, handed to it at boot (OmnibridgeBundle::boot(), or the bundle of
 * an application that runs this pass with its own list - omnibase's
 * BaseBundle): each contract by its interface's name, the service the
 * container binds to that name - this package's adapters (config/services.php),
 * the framework's clock and event dispatcher, or an application's own
 * binding, which wins as any alias does.
 *
 * Only the contracts whose service exists are listed (no clock without
 * symfony/clock, no Access without SecurityBundle): the registry says it has
 * none rather than failing when the service is built. An adapter of this
 * package (tagged omnibridge.adapter) whose framework service is missing -
 * SecurityAccess in an application without SecurityBundle, CsrfTokens without
 * CSRF protection - is removed with the aliases that lead to it, so a
 * container without them still compiles. A locator holds closures: nothing is
 * constructed before a contract is asked.
 *
 * Moved from omnisense/omnibase's Base\DependencyInjection\Compiler\Pass\ContractsPass
 * (plan "omnibase agnostique", A1), which now runs this one with omnibase's
 * own contracts added.
 */
class ContractsPass implements CompilerPassInterface
{
    public const SERVICE = 'omnibridge.contracts';

    /** The tag of this package's adapters: removed when a service they need is missing. */
    public const ADAPTER = 'omnibridge.adapter';

    /** @var list<class-string> the host's contracts (omnisense/omnibridge), PSR-20's clock and PSR-14's dispatcher */
    public const CONTRACTS = [
        Urls::class,
        Routes::class,
        Environment::class,
        Translations::class,
        Access::class,
        Sessions::class,
        Locales::class,
        Files::class,
        Tokens::class,
        ClockInterface::class,
        EventDispatcherInterface::class,
    ];

    /**
     * @param list<string>          $contracts the names the locator answers
     * @param array<string, string> $services  a contract bound to another service than its name
     * @param array<string, string> $former    a former name => the contract it is listed as, the same service
     * @param string                $id        the locator's service id
     */
    public function __construct(
        private readonly array $contracts = self::CONTRACTS,
        private readonly array $services = [],
        private readonly array $former = [],
        private readonly string $id = self::SERVICE,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::ADAPTER)) as $adapter) {
            if (!self::isResolvable($container, $container->getDefinition($adapter))) {
                self::remove($container, $adapter);
            }
        }

        $contracts = [];
        foreach ($this->contracts as $contract) {
            $id = $this->services[$contract] ?? $contract;
            if ($container->has($id)) {
                $contracts[$contract] = new Reference($id);
            }
        }
        foreach ($this->former as $former => $contract) {
            if (isset($contracts[$contract])) {
                $contracts[$former] = $contracts[$contract];
            }
        }

        $container->register($this->id, ServiceLocator::class)
            ->setPublic(true)
            ->addTag('container.service_locator')
            ->setArguments([$contracts]);
    }

    /** Whether every service the definition's arguments require exists (those it may do without aside). */
    private static function isResolvable(ContainerBuilder $container, Definition $definition): bool
    {
        foreach ($definition->getArguments() as $argument) {
            if ($argument instanceof Reference
                && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $argument->getInvalidBehavior()
                && !$container->has((string) $argument)) {
                return false;
            }
            if ($argument instanceof Definition && !self::isResolvable($container, $argument)) {
                return false;
            }
        }

        return true;
    }

    /** The service and every alias that leads to it, through other aliases as well. */
    private static function remove(ContainerBuilder $container, string $id): void
    {
        $container->removeDefinition($id);
        $gone = [$id => true];
        do {
            $removed = false;
            foreach ($container->getAliases() as $alias => $target) {
                if (isset($gone[(string) $target])) {
                    $container->removeAlias($alias);
                    $gone[$alias] = $removed = true;
                }
            }
        } while ($removed);
    }
}
