<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * WebauthnBundle's own Doctrine mapping turned off (plan "omniauth", AU1).
 *
 * The bundle maps, in XML, webauthn-lib's Webauthn\CredentialRecord and two
 * entities of the library as mapped superclasses, for an application's
 * credential entity to inherit (WebauthnBundle::build() adds the driver to
 * every entity manager's chain, under the namespace "Webauthn", whatever the
 * configuration). The core describes the passkeys' table itself since AU1:
 * Base\Entity\User\Passkey declares its 13 fields and no longer extends the
 * record, and no class of a site does (searched). The driver would only list
 * three classes that nothing maps, which Symfony alone would know (the Laravel
 * twin, or an entity manager built by hand, has no bundle) - so it is taken
 * out of the chains, after the bundle's pass. Its DBAL types stay registered
 * (a site's own column might name them); the core's columns use its own.
 */
class WebauthnMappingPass implements CompilerPassInterface
{
    private const NAMESPACE = 'Webauthn';

    /** After WebauthnBundle's DoctrineOrmMappingsPass (priority 0, before optimization). */
    public static function getPriority(): int
    {
        return -100;
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!preg_match('/^doctrine\.orm\.[^.]+_metadata_driver$/', $id) || !$definition->hasMethodCall('addDriver')) {
                continue;
            }

            $calls = array_values(array_filter(
                $definition->getMethodCalls(),
                static fn (array $call) => !('addDriver' === $call[0] && self::NAMESPACE === ($call[1][1] ?? null)),
            ));
            $definition->setMethodCalls($calls);
        }
    }
}
