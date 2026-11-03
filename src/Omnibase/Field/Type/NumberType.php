<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Form\Transformer\ScaleNumberTransformer;
use Omnibridge\Symfony\Omnibase\Form\Transformer\StrippedNumberToLocalizedStringTransformer;
use Base\Field\Behaviour\Number;
use Symfony\Component\Form\Extension\Core\DataTransformer\StringToFloatTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberType extends \Symfony\Component\Form\Extension\Core\Type\NumberType
{
    public function getBlockPrefix(): string
    {
        return 'number2';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new ScaleNumberTransformer($options["divisor"]));
        $builder->addViewTransformer(new StrippedNumberToLocalizedStringTransformer(
            $options["prefix"],
            $options["suffix"],
            $options['scale'],
            $options['grouping'],
            $options['rounding_mode'],
            $options['html5'] ? 'en' : null
        ));

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new StringToFloatTransformer($options['scale']));
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        // The steppers' settings, the neutral behaviour's (Base\Field\Behaviour\Number).
        $view->vars = array_replace($view->vars, (new Number())->vars($options, $view->vars["value"] ?? null, $view->vars["id"]));
        $view->vars["disabled"] = $options["disabled"];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array_diff_key((new Number())->options([]), array_flip(['scale', 'grouping', 'rounding_mode', 'html5', 'input', 'disabled'])));
    }
}
