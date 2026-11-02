<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Omnibridge\Symfony\Omnibase\Field\Type\AvatarType;
use Omnibridge\Symfony\Omnibase\Field\Type\PasswordType;
use Base\Form\Model\UserProfileModel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Util\StringUtil;

class UserProfileType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserProfileModel::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('avatar', AvatarType::class, ["required" => false, "cropper" => []]);
        $builder->add('email', EmailType::class, ["required" => false]);
        $builder->add('plainPassword', PasswordType::class, ["required" => false]);
    }
}
