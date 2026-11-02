<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Omnibridge\Symfony\Omnibase\Form\Traits\FormGuessInterface;
use Symfony\Component\Form\FormFactoryInterface as SymfonyFormFactoryInterface;

interface FormFactoryInterface extends SymfonyFormFactoryInterface,
    FormGuessInterface
{
}
