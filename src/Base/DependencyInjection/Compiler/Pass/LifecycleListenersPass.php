<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Contract\Lifecycle\Listeners;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The listeners of omnibase's neutral lifecycle (Base\Contract\Lifecycle\*: PrePersist, PostLoad, OnFlush...) found by
 * the layout every omnibase site shares, without a tag: a service whose class is in an `EntitySubscriber` folder
 * (`App\EntitySubscriber\X`, a bundle's `Base\Blog\EntitySubscriber\X`) and is a callable `(object $event): void`
 * (`__invoke`) is tagged Listeners::TAG (`omnibridge.lifecycle_listener`), and the persistence chosen hands it every
 * event (omnibridge/doctrine's LifecycleRelay, omnibridge/eloquent's own flush). omnibridge/laravel reads the same
 * folders (Omnibase\OmnibaseServices). A subscriber of omnibase's named events (getSubscribedEvents()) is not one.
 */
class LifecycleListenersPass implements CompilerPassInterface
{
    public const FOLDER = '\\EntitySubscriber\\';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if (null === $class || $definition->isAbstract() || $definition->hasTag(Listeners::TAG) || !self::listens($container->getParameterBag()->resolveValue($class))) {
                continue;
            }
            $definition->addTag(Listeners::TAG);
        }
    }

    /** Whether a class is a listener of the neutral lifecycle by the layout: an invokable of an EntitySubscriber folder. */
    public static function listens(mixed $class): bool
    {
        return \is_string($class) && str_contains($class, self::FOLDER) && class_exists($class) && method_exists($class, '__invoke') && !method_exists($class, 'getSubscribedEvents');
    }
}
