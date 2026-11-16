<?php

namespace Base\Field\Type;

use Symfony\Component\Form\Extension\Core\Type\BaseType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\SubmitButtonTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ButtonType extends BaseType implements SubmitButtonTypeInterface // NB: A button is also a submit...
{                                                                      //     If you change type attribute..
    public function getParent(): ?string
    {
        return \Symfony\Component\Form\Extension\Core\Type\ButtonType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'button2';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('auto_initialize', false);
        $resolver->setDefault('confirmation', false);
        $resolver->setDefault('confirmation-bubbleup', true);
        $resolver->setDefault('label_html', true);
        $resolver->setDefault('type', "button");
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars = array_replace($view->vars, [
            "type" => $options["type"],
            "confirmation" => $options["confirmation"],
            "confirmation_bubbleup" => $options["confirmation-bubbleup"]
        ]);
    }
}
