<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Controller\Attribute\MapRecord;
use Base\Database\Repository\Finders;
use Base\Database\Repository\Repositories;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An action's argument typed with one of the persistence's entities, read from the route (Base\Controller\Attribute\
 * MapRecord, or by default the route parameter of the argument's name, else `id`) through omnibase's repositories: the
 * neutral form of symfony/doctrine-bridge's EntityValueResolver, on whichever persistence. A record not found is a 404;
 * a route without the parameter leaves the argument to its default (null where it is nullable).
 */
final class RecordArguments implements ValueResolverInterface
{
    public function __construct(private readonly Finders $finders, private readonly Repositories $repositories)
    {
    }

    /** @return iterable<object|null> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $class = $argument->getType();
        if (null === $class || !class_exists($class) || !$this->finders->shapes()->has($class) || $request->attributes->has($argument->getName()) && \is_object($request->attributes->get($argument->getName()))) {
            return [];
        }
        $attributes = $argument->getAttributes(MapRecord::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = $attributes[0] ?? new MapRecord();
        \assert($options instanceof MapRecord);
        if ($options->disabled) {
            return [];
        }

        $record = $this->find($request, $argument, $class, $options);
        if (null === $record) {
            if (null !== $options->expr || $this->hasParameter($request, $argument, $options)) {
                throw new NotFoundHttpException(\sprintf('"%s" object not found by "%s".', $class, self::class));
            }

            return $argument->isNullable() || $argument->hasDefaultValue() ? [null] : [];
        }

        return [$record];
    }

    private function hasParameter(Request $request, ArgumentMetadata $argument, MapRecord $options): bool
    {
        if (null !== $options->mapping) {
            return [] !== $options->mapping;
        }

        return $request->attributes->has($options->id ?? $argument->getName()) || (null === $options->id && $request->attributes->has('id'));
    }

    private function find(Request $request, ArgumentMetadata $argument, string $class, MapRecord $options): ?object
    {
        $repository = $this->repositories->of($class);
        if (null !== $options->expr) {
            if (!preg_match('/^(\w+)\((.*)\)$/', $options->expr, $call)) {
                throw new \LogicException(\sprintf('MapRecord expr "%s" is not a finder call "method(param, ...)".', $options->expr));
            }
            $arguments = array_map(static fn (string $name) => $request->attributes->get(trim($name)), array_filter(explode(',', $call[2]), static fn (string $name): bool => '' !== $name));

            return $repository->{$call[1]}(...$arguments);
        }
        if (null !== $options->mapping) {
            $criteria = [];
            foreach ($options->mapping as $parameter => $field) {
                if (!$request->attributes->has($parameter)) {
                    return null;
                }
                $criteria[$field] = $request->attributes->get($parameter);
            }

            return [] === $criteria ? null : $repository->findOneBy($criteria);
        }
        foreach (array_filter([$options->id ?? $argument->getName(), null === $options->id ? 'id' : null]) as $parameter) {
            if ($request->attributes->has($parameter)) {
                $id = $request->attributes->get($parameter);

                return \is_scalar($id) ? $repository->find($id) : null;
            }
        }

        return null;
    }
}
