<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Omnibase\Form\DefinitionType;
use Symfony\Component\Form\Util\StringUtil;

class SecurityRegistrationType extends DefinitionType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    protected function definition(array $options): FormDefinition
    {
        return AccountForms::signUp();
    }
}
