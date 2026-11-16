<?php

namespace Base\Field\Type;

use Base\Enum\ThreadState;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StateType extends AbstractType
{
    public function getParent(): ?string
    {
        return SelectType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'state';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => ThreadState::class,
            'empty_data' => ThreadState::DRAFT
        ]);
    }
}
