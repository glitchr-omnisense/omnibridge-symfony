<?php

namespace Base\Field\Type;

use Base\Enum\UserRole;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function getParent(): ?string
    {
        return SelectType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'role';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "capitalize" => true,
            'class' => UserRole::class,
            'empty_data' => UserRole::USER
        ]);
    }
}
