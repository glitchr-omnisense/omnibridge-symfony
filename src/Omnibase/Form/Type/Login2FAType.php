<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The six-digit code that confirms a new authenticator app (/settings/2fa):
 * nothing is mapped, the controller checks the code against the pending secret.
 */
class Login2FAType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => '2fa',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'mapped' => false,
            'label' => '@forms.2fa.code',
            'attr' => [
                'inputmode' => 'numeric',
                'autocomplete' => 'one-time-code',
                'maxlength' => 6,
                'placeholder' => '000000',
            ],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(exactly: 6),
            ],
        ]);
    }
}
