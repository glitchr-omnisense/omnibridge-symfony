<?php

namespace Base\Form\Common;

use Symfony\Component\Form\FormTypeInterface as SymfonyFormTypeInterface;

interface FormTypeInterface extends SymfonyFormTypeInterface
{
    public static function getModelClass(): string;
}
