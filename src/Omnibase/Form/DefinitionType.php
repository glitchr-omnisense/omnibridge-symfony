<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Base\Form\Definition\FormDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A form type that is a neutral FormDefinition: a site's form known to Symfony by its class - its
 * name, its block prefix, FormProxy::createProcessor(), the options only Symfony reads - and
 * described once for every host by definition(). The definition's form options are this type's
 * defaults; what a subclass sets after parent::configureOptions() wins.
 */
abstract class DefinitionType extends AbstractType
{
    /** @param array<string, mixed> $options this form's options; none while its defaults are asked */
    abstract protected function definition(array $options): FormDefinition;

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The name and the method stay Symfony's: a type is asked by its class, and `method` is its own default.
        $definition = $this->definition([]);
        $defaults = DefinitionForms::formOptions($definition);
        unset($defaults['method'], $defaults['csrf_protection']);
        if (!$definition->hasIntent()) {
            unset($defaults['csrf_token_id']);
        }
        $resolver->setDefaults($defaults);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        DefinitionForms::populate($builder, $this->definition($options));
    }
}
