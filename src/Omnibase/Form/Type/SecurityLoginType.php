<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Omnibridge\Symfony\Omnibase\Form\Common\AbstractType;
use Base\Form\Model\SecurityLoginModel;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Util\StringUtil;

class SecurityLoginType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SecurityLoginModel::class,
            'identifier' => null, // to pass variable from controller to Type
            'allow_extra_fields' => true,
            'allow_login_token' => false
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["allow_login_token"] = $options["allow_login_token"];  
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', TextType::class, [
                'attr' => [
                    'id' => "inputUsername",  // used in Symfony kernel
                    'value' => $options["identifier"] ?? ""
                ]
            ])
            ->add('password', PasswordType::class, [
                'attr' => [
                    'id' => "inputPassword"  // used in Symfony kernel
                ]
            ])
            ->add("_remember_me", CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ["checked" => "checked"]
            ]);
    }
}
