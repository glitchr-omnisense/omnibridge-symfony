<?php

namespace Omnibridge\Symfony\Omnibase\Form;

/**
 * A form type named in a string, compared with a class, a leading backslash aside. 1.x compared a type under
 * either of its names (omnisense/omnibase 5.x aliased the former ones, Base\Field\Type\X); since 2.0, with
 * omnisense/omnibase 6.0, a type has one name.
 */
final class FormTypes
{
    private function __construct()
    {
    }

    /** The name of a type, without a leading backslash. */
    public static function declared(string $type): string
    {
        return ltrim($type, '\\');
    }

    /** Whether $type names $class. */
    public static function same(mixed $type, string $class): bool
    {
        return \is_string($type) && self::declared($type) === self::declared($class);
    }

    /**
     * Whether $type names one of $classes.
     *
     * @param list<string> $classes
     */
    public static function in(mixed $type, array $classes): bool
    {
        foreach ($classes as $class) {
            if (self::same($type, $class)) {
                return true;
            }
        }

        return false;
    }
}
