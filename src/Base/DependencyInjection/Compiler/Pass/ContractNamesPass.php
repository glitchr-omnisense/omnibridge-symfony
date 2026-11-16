<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Contract\Contracts;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The host's contracts under the names an application writes: each service the container holds under a name of
 * omnisense/omnibridge (Omnibridge\Contract\Urls, Omnibridge\Persistence\UnitOfWork...) answers its Base\ name too
 * (Base\Contract\Contracts::NAMES: one type, two names), so that a constructor typed Base\Contract\Urls is autowired
 * and `Base\Contract\Urls` is a service id. An application's own service under the Base\ name is left alone.
 */
class ContractNamesPass implements CompilerPassInterface
{
    /** Before the services are autowired: the aliases are what a Base\ type-hint resolves to. */
    public static function getPriority(): int
    {
        return 64;
    }

    public function process(ContainerBuilder $container): void
    {
        foreach (Contracts::NAMES as $name => $contract) {
            if ($container->has($contract) && !$container->has($name)) {
                $public = $container->hasAlias($contract) ? $container->getAlias($contract)->isPublic() : $container->getDefinition($contract)->isPublic();
                $container->setAlias($name, new Alias($contract, $public));
            }
        }
    }
}
