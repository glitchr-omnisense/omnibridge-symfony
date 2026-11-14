<?php

namespace Omnibridge\Symfony\Omnibase\Service;

use Base\Service\HotParameterBagInterface;
use Base\Service\HotParameters;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface as SymfonyParameterBagInterface;

/**
 * `parameter_bag` under Symfony: the container's parameters (Omnibridge\Symfony\Omnibase\Service\ParameterBag)
 * with the settings stored in the database laid over them - Base\Service\HotParameters,
 * the Foundation's overlay, as DependencyInjection's ParameterBagInterface and
 * ContainerBagInterface (plan "omnibase agnostique", C9). It decorates `parameter_bag`
 * over Omnibridge\Symfony\Omnibase\Service\ParameterBag; `parameter_bag`, the autowiring of
 * Base\Service\ParameterBagInterface (and of DependencyInjection's two interfaces) and
 * Runtime::parameters() are this one object.
 *
 * Like the frozen bag the container's parameters are, it refuses clear() and remove();
 * add() takes the settings in (HotParameterBagSubscriber, through HotParameters::warm()), set() writes
 * into the $bag given.
 *
 * Moved from omnisense/omnibase's Base\Service\HotParameterBag (plan "omnibase agnostic", step F5c),
 * its former name an alias the core declares: the overlay is the core's HotParameters, this is its
 * Symfony container side. HotParameterBagInterface is omnibase's ParameterBagInterface now, so this
 * class names DependencyInjection's itself.
 */
class HotParameterBag extends HotParameters implements HotParameterBagInterface, SymfonyParameterBagInterface, ContainerBagInterface
{
    /** @param ParameterBag|Container $config the container's parameters, or the container to read them from */
    public function __construct(ParameterBag|Container $config, ?bool $enabled = null)
    {
        parent::__construct($config instanceof Container ? new ParameterBag($config) : $config, $enabled);
    }

    public function clear(): void
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    public function remove(string $name): void
    {
        throw new LogicException('Impossible to call remove() on a frozen ParameterBag.');
    }

    /** The container's parameters are resolved already. */
    public function resolve(): void
    {
    }

    public function resolveValue(mixed $value): mixed
    {
        return $this->config->resolveValue($value);
    }

    public function escapeValue(mixed $value): mixed
    {
        return $this->config->escapeValue($value);
    }

    public function unescapeValue(mixed $value): mixed
    {
        return $this->config->unescapeValue($value);
    }
}
