<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Base\Database\FormerNames;

/**
 * A form type named in a string, compared with a class: the same type under either of its names. The
 * form types moved here from omnisense/omnibase keep their former names (Base\Field\Type\X is
 * Omnibridge\Symfony\Omnibase\Field\Type\X until omnisense/omnibase 6.0, the core's
 * former-names.symfony.php), a field's options may name either (SymfonyKinds gives a kind's type by
 * its former name, a site's settings or fields name what they import), so a plain `===` on one name
 * misses the other. Read from the core's alias map (Base\Database\FormerNames, as
 * FormerFormTypesPass does): nothing is autoloaded to compare.
 */
final class FormTypes
{
    /** @var array<string, string>|null former => new */
    private static ?array $moved = null;

    private function __construct()
    {
    }

    /** The declared name of a type: the new name for a former one, any other name as it is. */
    public static function declared(string $type): string
    {
        $type = ltrim($type, '\\');
        self::$moved ??= class_exists(FormerNames::class) ? FormerNames::moved() : [];

        return self::$moved[$type] ?? $type;
    }

    /** Whether $type names $class, by either of its names. */
    public static function same(mixed $type, string $class): bool
    {
        return \is_string($type) && self::declared($type) === self::declared($class);
    }

    /**
     * Whether $type names one of $classes, by either of its names.
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
