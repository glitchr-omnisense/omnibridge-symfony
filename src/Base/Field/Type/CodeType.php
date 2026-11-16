<?php

namespace Base\Field\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A code of known length - a one-time code, a voucher, a PIN - typed one
 * character per box. The boxes are presentation only: they feed a single
 * hidden input that carries the field's real value, so the type is a plain
 * TextType to the form (validation, data transformers, everything) and
 * degrades to that hidden value when the script is not there.
 *
 * Options: length (6), alphabet ('alnum' | 'digits' | 'alpha'), uppercase
 * (true), submit_on_complete (false: submit the form once every box is
 * filled - what a login code page wants).
 */
final class CodeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'length' => 6,
            'alphabet' => 'alnum',
            'uppercase' => true,
            'submit_on_complete' => false,
            'webpack_entry' => 'form.code',
        ]);
        $resolver->setAllowedTypes('length', 'int');
        $resolver->setAllowedValues('alphabet', ['alnum', 'digits', 'alpha']);
        $resolver->setAllowedTypes('uppercase', 'bool');
        $resolver->setAllowedTypes('submit_on_complete', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['code_length'] = max(1, $options['length']);
        $view->vars['code_alphabet'] = $options['alphabet'];
        $view->vars['code_uppercase'] = $options['uppercase'];
        $view->vars['code_submit'] = $options['submit_on_complete'];
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'code';
    }
}
