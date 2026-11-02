<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

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
        $resolver->setDefaults([
            'jscolor' => [],
            'is_nullable' => true,
            "webpack_entry" => "form.color"
        ]);
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $event->setData(expandhex($event->getData(), true));

            if ($event->getData() === null) {
                $event->setData("#00000000");
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            $event->setData(expandhex($event->getData(), true));

            if ($event->getData() == "#00000000" && $options["is_nullable"]) {
                $event->setData(null);
            }

            $event->setData(shrinkhex($event->getData()));
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // JSColor requirement
        $view->vars['attr']['data-jscolor'] = json_encode($options["jscolor"]);

        // JSColor class for stylsheet
        $view->vars['attr']["class"] = "form-color";

        // Add alpha channel by default
        $view->vars["value"] = expandhex($view->vars["value"], true);
    }
}
