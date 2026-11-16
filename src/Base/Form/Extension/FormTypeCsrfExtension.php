<?php

namespace Base\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractTypeExtension;

class FormTypeCsrfExtension extends AbstractTypeExtension
{
    protected bool $defaultEnabled;

    public function __construct(bool $defaultEnabled = false)
    {
        $this->defaultEnabled = $defaultEnabled;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name' => '_csrf_token'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
