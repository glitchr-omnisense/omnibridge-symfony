<?php

namespace Base\Field\Type;

use Base\Enum\UserGender as Gender;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GenderType extends AbstractType
{
    public function getParent(): ?string
    {
        return SelectType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'gender';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Gender::class,
            'empty_data' => Gender::GENDERLESS
        ]);
    }
}
