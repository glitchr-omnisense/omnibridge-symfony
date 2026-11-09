<?php

namespace Omnibridge\Symfony\Omnibase\Form\Common;

/**
 * Where a form type's model lives, for the option `use_model`: beside the type by name (App\Form\Type\XType's
 * App\Form\Model\XModel), else, for omnibase's types, which moved to this bridge (Omnibridge\Symfony\Omnibase\Form\Type\*)
 * while their models stayed in the core (Base\Form\Model\*), in the core's namespace.
 *
 *     FormModels::resolve('Omnibridge\Symfony\Omnibase\Form\Model\ContactModel'); // Base\Form\Model\ContactModel
 *     FormModels::resolve('App\Form\Model\ContactModel');                         // itself
 */
final class FormModels
{
    /** The bridge's namespace of omnibase's moved classes, and the core's they came from. */
    private const MOVED = 'Omnibridge\\Symfony\\Omnibase\\';
    private const CORE = 'Base\\';

    /**
     * The model named $model by a type's convention, where it lives: itself when it exists, else the core's class of the
     * same name when it is named in the bridge's namespace and the core has it, else itself (for the caller's message).
     */
    public static function resolve(string $model): string
    {
        if (!class_exists($model) && str_starts_with($model, self::MOVED)) {
            $core = self::CORE.substr($model, \strlen(self::MOVED));
            if (class_exists($core)) {
                return $core;
            }
        }

        return $model;
    }
}
