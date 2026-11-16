<?php

namespace Base\Form\Common;

abstract class AbstractType extends \Symfony\Component\Form\AbstractType implements FormTypeInterface
{
    public static function getModelClass(): string
    {
        return str_replace("Type", "Model", static::class);
    }
}
