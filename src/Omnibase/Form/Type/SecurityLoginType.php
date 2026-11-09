<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Omnibase\Form\Common\FormModels;
use Omnibridge\Symfony\Omnibase\Form\Common\FormTypeInterface;
use Omnibridge\Symfony\Omnibase\Form\DefinitionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Util\StringUtil;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecurityLoginType extends DefinitionType implements FormTypeInterface
{
    public static function getModelClass(): string
    {
        return FormModels::resolve(str_replace("Type", "Model", static::class));
    }

    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'identifier' => null, // to pass variable from controller to Type
            'allow_extra_fields' => true,
            'allow_login_token' => false
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["allow_login_token"] = $options["allow_login_token"];
    }

    protected function definition(array $options): FormDefinition
    {
        return AccountForms::signIn($options["identifier"] ?? null);
    }
}
