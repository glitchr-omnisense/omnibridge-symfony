<?php

namespace Omnibridge\Symfony\Omnibase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * What an action written once for every host is given under Symfony beyond
 * its route's parameters (step R27 of the audit "lesringostars face au
 * master plan"): `array $query`, the query string's values, and `array $body`
 * (step R29), what a POST sent - its JSON object, else its form fields -, as
 * omnibridge/laravel's Screen\Responder::action() gives them. A route
 * parameter of either name keeps its own.
 */
final class ActionArguments implements ValueResolverInterface
{
    /** @return iterable<array<string, mixed>> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $name = $argument->getName();
        if (!\in_array($name, ['query', 'body'], true) || 'array' !== $argument->getType() || $request->attributes->has($name)) {
            return [];
        }

        return ['query' === $name ? $request->query->all() : self::body($request)];
    }

    /**
     * What a request's body carries: its JSON object (a script's request), else its form fields.
     *
     * @return array<string, mixed>
     */
    public static function body(Request $request): array
    {
        $values = json_decode((string) $request->getContent(), true);

        return array_filter(\is_array($values) ? $values : $request->request->all(), '\is_string', \ARRAY_FILTER_USE_KEY);
    }
}
