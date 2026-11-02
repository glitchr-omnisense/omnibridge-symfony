<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;

class SecurityResetPasswordConfirmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'validation_groups' => ["edit"],
                'mapped' => false]);
    }
}
