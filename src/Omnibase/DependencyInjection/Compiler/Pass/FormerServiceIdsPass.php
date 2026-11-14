<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Base\Database\FormerNames;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A moved class's service under both of its names. The services of the classes that moved here from
 * omnisense/omnibase (plan "omnibase agnostic", steps F5 to S) are still registered under their former
 * ids (Base\Bridge\Symfony\ScreenResponder, Base\Subscriber\..., Base\Security\...; the core's
 * former-names.symfony.php until 6.0), what the sites and the bundles named them by. A constructor
 * typed on the new name (Omnibridge\Symfony\Omnibase\ScreenResponder) found no service: autowired null
 * where nullable, refused otherwise. This pass gives each such service its new name as an alias of the
 * former id, public where the former is: one service, the same instance under either name. A new name
 * the container already has (a definition or an alias of its own) is left as it is.
 *
 * Before the optimisation passes (autowiring), after the other passes that register services.
 */
final class FormerServiceIdsPass implements CompilerPassInterface
{
    public static function getPriority(): int
    {
        return -128;
    }

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(FormerNames::class)) {
            return;
        }

        foreach (FormerNames::moved() as $former => $new) {
            if ($container->hasDefinition($new) || $container->hasAlias($new)) {
                continue;
            }
            if ($container->hasDefinition($former)) {
                $definition = $container->getDefinition($former);
                if ($definition->isAbstract()) {
                    continue;
                }
                $public = $definition->isPublic();
            } elseif ($container->hasAlias($former)) {
                $public = $container->getAlias($former)->isPublic();
            } else {
                continue;
            }

            $container->setAlias($new, $former)->setPublic($public);
        }
    }
}
