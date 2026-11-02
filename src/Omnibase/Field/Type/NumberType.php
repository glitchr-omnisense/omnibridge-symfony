<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Form\Transformer\ScaleNumberTransformer;
use Omnibridge\Symfony\Omnibase\Form\Transformer\StrippedNumberToLocalizedStringTransformer;
use Twig\Environment;
use Symfony\Component\Form\Extension\Core\DataTransformer\StringToFloatTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberType extends \Symfony\Component\Form\Extension\Core\Type\NumberType
{
    /**
     * @var Environment
     */
    protected Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

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

        $view->vars["stepUp"] = $options["stepUp"] ?? $options["step"];
        $view->vars["stepDown"] = $options["stepDown"] ?? $options["step"];
        $view->vars["keyUp"] = $options["keyUp"];
        $view->vars["keyDown"] = $options["keyDown"];
        $view->vars["throttleUp"] = $options["throttleUp"] ?? $options["throttle"];
        $view->vars["throttleDown"] = $options["throttleDown"] ?? $options["throttle"];
        $view->vars["min"] = $options["min"];
        $view->vars["divisor"] = $options["divisor"];
        $view->vars["max"] = $options["max"];
        $view->vars["suffix"] = $options["suffix"];
        $view->vars["inline"] = $options["inline"];
        $view->vars["prefix"] = $options["prefix"];
        $view->vars["disabled"] = $options["disabled"];
        $view->vars["autocomplete"] = $options["autocomplete"];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'stepUp' => null,
            'stepDown' => null,
            'divisor' => 1,
            'step' => 1,
            'throttleUp' => null,
            'throttleDown' => null,
            'inline' => false,
            'throttle' => 50,
            "min" => null,
            "max" => null,
            "suffix" => null,
            "prefix" => null,
            "autocomplete" => false,
            "keyUp" => true,
            "keyDown" => true,
        ]);
    }
}
