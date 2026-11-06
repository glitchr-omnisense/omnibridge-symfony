<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection;

use Base\Services;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * omnibase's services, declared by the core as data (Base\Services, plan "omnibase agnostic", step F5e
 * slice 4), compiled into Symfony's container: a group at a time, in its place among this bridge's own
 * definitions (config/omnibase/services/*.php), so that the container - the order of the services a tag
 * collects included - is the one the former Symfony files built. A definition's `symfony` part is what
 * only Symfony's container gives it (its arguments and calls, which replace the neutral ones, its parent,
 * factory, bindings and decoration). Nothing here decides which service exists: the core's declaration does,
 * with Services::applies() (the packages it requires, the environment it names).
 */
final class Declarations
{
    /** Each definition of a group the core declares, defined in the builder, its contracts aliased. */
    public static function group(ContainerBuilder $builder, string $group): void
    {
        $environment = $builder->hasParameter('kernel.environment') ? $builder->getParameter('kernel.environment') : null;
        $file = \dirname((new \ReflectionClass(Services::class))->getFileName()).'/Resources/services/'.$group.'.php';
        foreach (Services::group($group) as $declaration) {
            if (!Services::applies($declaration, \is_string($environment) ? $environment : null)) {
                continue;
            }
            $id = Services::id($declaration);
            $builder->setDefinition($id, self::definition($declaration, $file));
            foreach (Services::contracts($declaration) as $alias => $options) {
                $builder->setAlias($alias, new Alias($options['target'] ?? $id, $options['public'] ?? false));
            }
        }
    }

    /** @param array<string, mixed> $declaration */
    public static function definition(array $declaration, string $file = ''): Definition
    {
        $symfony = $declaration['symfony'] ?? [];
        $parent = $symfony['parent'] ?? $declaration['parent'] ?? null;
        $definition = null !== $parent ? new ChildDefinition($parent) : new Definition();
        if (isset($declaration['class'])) {
            $definition->setClass($declaration['class']);
        }
        foreach (['public' => 'setPublic', 'shared' => 'setShared', 'lazy' => 'setLazy', 'abstract' => 'setAbstract', 'autowire' => 'setAutowired', 'autoconfigure' => 'setAutoconfigured'] as $key => $setter) {
            if (\array_key_exists($key, $declaration)) {
                $definition->$setter($declaration[$key]);
            }
        }
        $arguments = $symfony['arguments'] ?? $declaration['arguments'] ?? [];
        if ($arguments) {
            $definition->setArguments(self::value($arguments));
        }
        $bindings = [];
        foreach (($declaration['bind'] ?? []) + ($symfony['bind'] ?? []) as $name => $value) {
            $bindings[$name] = new BoundArgument(self::value($value), true, BoundArgument::SERVICE_BINDING, $file);
        }
        if ($bindings) {
            $definition->setBindings($bindings);
        }
        if (null !== $factory = $symfony['factory'] ?? $declaration['factory'] ?? null) {
            $definition->setFactory(self::value($factory));
        }
        foreach ($symfony['calls'] ?? $declaration['calls'] ?? [] as $call) {
            $definition->addMethodCall($call[0], self::value($call[1] ?? []), $call[2] ?? false);
        }
        if (null !== $decorates = $symfony['decorates'] ?? $declaration['decorates'] ?? null) {
            $definition->setDecoratedService($decorates['id'], $decorates['inner'] ?? null, $decorates['priority'] ?? 0, match ($decorates['on_invalid'] ?? null) {
                'ignore' => ContainerInterface::IGNORE_ON_INVALID_REFERENCE,
                'null' => ContainerInterface::NULL_ON_INVALID_REFERENCE,
                default => ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            });
        }
        foreach ($declaration['tags'] ?? [] as $tag) {
            \is_string($tag) ? $definition->addTag($tag) : $definition->addTag($tag['name'], array_diff_key($tag, ['name' => true]));
        }

        return $definition;
    }

    /** A value of the declaration as Symfony's container takes it ('@id' a Reference, the `!` forms their arguments). */
    public static function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            if (1 === \count($value) && \is_string($key = array_key_first($value)) && str_starts_with($key, '!')) {
                $inner = $value[$key];

                return match ($key) {
                    '!tagged_iterator' => new TaggedIteratorArgument($inner),
                    '!service_closure' => new ServiceClosureArgument(self::value($inner)),
                    '!iterator' => new IteratorArgument(self::value($inner)),
                    '!closure' => (new Definition('Closure'))->setFactory(['Closure', 'fromCallable'])->setArguments([self::value($inner)]),
                    '!new' => new Definition($inner['class'], self::value($inner['arguments'] ?? [])),
                    default => throw new \InvalidArgumentException(sprintf('Unknown value "%s" in omnibase\'s declaration of services.', $key)),
                };
            }

            return array_map(self::value(...), $value);
        }
        if (!\is_string($value) || !str_starts_with($value, '@')) {
            return $value;
        }
        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }
        foreach (['@??' => ContainerInterface::IGNORE_ON_INVALID_REFERENCE, '@?' => ContainerInterface::NULL_ON_INVALID_REFERENCE, '@!' => ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE] as $prefix => $behaviour) {
            if (str_starts_with($value, $prefix)) {
                return new Reference(substr($value, \strlen($prefix)), $behaviour);
            }
        }

        return new Reference(substr($value, 1));
    }
}
