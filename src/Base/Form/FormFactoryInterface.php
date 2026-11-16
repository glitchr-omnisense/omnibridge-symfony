<?php

namespace Base\Form;

use Base\Form\Traits\FormGuessInterface;
use Symfony\Component\Form\FormFactoryInterface as SymfonyFormFactoryInterface;

interface FormFactoryInterface extends SymfonyFormFactoryInterface,
    FormGuessInterface
{
}
