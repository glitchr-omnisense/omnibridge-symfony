<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BooleanType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'boolean';
    }

    public function getParent(): ?string
    {
        return CheckboxType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "confirmation[onCheck]" => true,
            "confirmation[onUncheck]" => true,
            "toogle_url" => null,
            "switch" => true,
            "inline" => false,
            "required" => false
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["switch"] = $options["switch"];
        $view->vars["confirmation_check"] = $options["confirmation[onCheck]"];
        $view->vars["confirmation_uncheck"] = $options["confirmation[onUncheck]"];
        $view->vars["toogle_url"] = $options["toogle_url"];
        $view->vars["inline"] = $options["inline"];
    }
}
