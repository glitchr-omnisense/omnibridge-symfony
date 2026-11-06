<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The firewalls' users on omnibridge/eloquent (`base.persistence: eloquent`; omnisense/omnibase's plan "omnibase
 * agnostic", step F7d): an entity user provider of security.yaml (`entity: { class: App\Entity\User }`, no `property`)
 * asks Doctrine's repository of the users - its loadUserByIdentifier(), refreshUser(), upgradePassword() (the core's
 * SecurityUserLoader and SecurityUserProvider). On Eloquent, the provider is the users' repository on Eloquent
 * itself (Base\Repository\Users), which answers the same methods the same way: the site's security.yaml unchanged. A
 * provider naming a `property` (Doctrine reads the user by it) or a `manager_name` is left to Doctrine.
 */
final class EloquentPersistencePass implements CompilerPassInterface
{
    private const DOCTRINE_PROVIDER = 'doctrine.orm.security.user.provider';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('base.persistence') || 'eloquent' !== $container->getParameter('base.persistence') || !$container->has('Base\Repository\Users')) {
            return;
        }
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'security.user.provider.concrete.') || !$definition instanceof ChildDefinition || self::DOCTRINE_PROVIDER !== $definition->getParent()) {
                continue;
            }
            $arguments = array_values($definition->getArguments());
            [$class, $property, $manager] = $arguments + [null, null, null];
            if (!\is_string($class) || !class_exists($class) || !is_a($class, 'Base\Entity\User', true) || null !== $property || null !== $manager) {
                continue;
            }
            $container->removeDefinition($id);
            $container->setAlias($id, new Alias('Base\Repository\Users'));
        }
    }
}
