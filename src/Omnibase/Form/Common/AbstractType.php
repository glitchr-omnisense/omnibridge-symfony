<?php

namespace Omnibridge\Symfony\Omnibase\Form\Common;

abstract class AbstractType extends \Symfony\Component\Form\AbstractType implements FormTypeInterface
{
    public static function getModelClass(): string
    {
        return FormModels::resolve(str_replace("Type", "Model", static::class));
    }
}
