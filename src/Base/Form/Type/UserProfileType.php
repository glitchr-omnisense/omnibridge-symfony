<?php

namespace Base\Form\Type;

use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormDefinition;
use Base\Form\DefinitionType;
use Symfony\Component\Form\Util\StringUtil;

class UserProfileType extends DefinitionType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    protected function definition(array $options): FormDefinition
    {
        return AccountForms::profile();
    }
}
