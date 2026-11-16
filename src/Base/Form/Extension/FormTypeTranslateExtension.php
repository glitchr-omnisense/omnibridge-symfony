<?php

namespace Base\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractTypeExtension;

class FormTypeTranslateExtension extends AbstractTypeExtension
{
    protected bool $defaultEnabled;

    public function __construct(bool $defaultEnabled = false)
    {
        $this->defaultEnabled = $defaultEnabled;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "translation_domain" => false
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
