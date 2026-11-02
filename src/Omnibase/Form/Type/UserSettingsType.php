<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;

use Base\Form\Model\UserSettingsModel;

class UserSettingsType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserSettingsModel::class
        ]);
    }
}
