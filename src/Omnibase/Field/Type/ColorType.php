<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\JsColor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Base\Service\ParameterBagInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
final class ColorType extends AbstractType
{

    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function getBlockPrefix(): string
    {
        return 'jscolor';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults((new JsColor())->options([]));
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    /** The neutral behaviour's value (Base\Field\Behaviour\JsColor): #RRGGBBAA shown, kept as short as written. */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $colour = new JsColor();
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options, $colour) {
            $event->setData($colour->transform($event->getData(), $options));
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options, $colour) {
            $event->setData($colour->reverse($event->getData(), $options));
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $colour = new JsColor();
        $vars = $colour->vars($options, $view->vars["value"], $view->vars["id"]);
        $view->vars['attr'] = array_replace($view->vars['attr'], $vars['attr']);
        $view->vars["value"] = expandhex($view->vars["value"], true);
    }
}
