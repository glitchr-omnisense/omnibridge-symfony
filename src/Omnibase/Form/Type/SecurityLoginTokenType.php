<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Omnibase\Form\DefinitionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Util\StringUtil;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The sign-in link's form (an address to send the link to), from omnibase's definition
 * (Base\Form\Definition\AccountForms::signInLink(): its model, its guard - a trap, the time, the
 * captcha, no list -, its field), as the other account forms (omnisense/omnibase's plan "omnibase
 * agnostic", step F5c).
 */
class SecurityLoginTokenType extends DefinitionType
{
    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'allow_login_token' => false,
            // The address shown in the field, which the controller may give.
            'email' => null,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["allow_login_token"] = $options["allow_login_token"];
    }

    protected function definition(array $options): FormDefinition
    {
        return AccountForms::signInLink($options['email'] ?? null);
    }
}
