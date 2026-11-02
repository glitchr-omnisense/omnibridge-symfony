<?php

namespace Omnibridge\Symfony\Omnibase\Service;

use Base\Service\DottedParameterBag;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface as SymfonyParameterBagInterface;
use UnitEnum;

/**
 * The Symfony adapter of omnibase's Base\Service\ParameterBagInterface: the
 * container's parameters (it decorates `parameter_bag`), read by a dotted path
 * as Base\Service\DottedParameterBag reads any (plan "omnibase agnostique", C9),
 * and DependencyInjection's ParameterBagInterface and ContainerBagInterface as
 * well, for the code that types those.
 *
 * It wraps Symfony's ContainerBag rather than extending it (since 4.0, P3):
 * ContainerBag is a FrozenParameterBag, whose set() and add() return never since
 * DependencyInjection 7, which PHP refuses against this class's void set(). What
 * the frozen bag forbade, this one forbids the same way (clear(), add(), remove()
 * throw its LogicException); what it read, it reads through the wrapped
 * ContainerBag. The settings stored in the database are laid over it by
 * HotParameterBag, which `parameter_bag` is.
 *
 * Moved from omnisense/omnibase's Base\Service\ParameterBag (plan "omnibase agnostic", step F5c),
 * its former name an alias the core declares.
 */
class ParameterBag extends DottedParameterBag implements SymfonyParameterBagInterface, ContainerBagInterface
{
    private readonly ContainerBag $containerBag;

    public function __construct(Container $container)
    {
        $this->containerBag = new ContainerBag($container);
    }

    protected function parameters(): array
    {
        return $this->containerBag->all();
    }

    /**
     * Writes into the $bag given; the container's parameters are frozen.
     *
     * @param array|null $bag
     */
    public function set(string $path, array|bool|string|int|float|UnitEnum|null $value, ?array &$bag = null): void
    {
        $this->write($path, $value, $bag);
    }

    public function clear(): void
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    public function add(array $parameters): void
    {
        throw new LogicException('Impossible to call add() on a frozen ParameterBag.');
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
        return $this->containerBag->resolveValue($value);
    }

    public function escapeValue(mixed $value): mixed
    {
        return $this->containerBag->escapeValue($value);
    }

    public function unescapeValue(mixed $value): mixed
    {
        return $this->containerBag->unescapeValue($value);
    }
}
