<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\ColorPickr;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Base\Service\TranslatorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ColorPickerType extends AbstractType
{
    public const THEME_CLASSIC = 'classic';
    public const THEME_MONOLITH = 'monolith';
    public const THEME_NANO = 'nano';

    public const POSITION_TOP = 'top';
    public const POSITION_LEFT = 'left';
    public const POSITION_BOTTOM = 'bottom';
    public const POSITION_RIGHT = 'right';
    public const POSITION_START = 'start';
    public const POSITION_MIDDLE = 'middle';
    public const POSITION_END = 'end';

    public const COLOR_NAVY = '#001F3F';
    public const COLOR_BLUE = '#0074d9';
    public const COLOR_AQUA = '#7fdbff';
    public const COLOR_TEAL = '#39cccc';
    public const COLOR_OLIVE = '#3d9970';
    public const COLOR_GREEN = '#2ecc40';
    public const COLOR_LIME = '#01ff70';
    public const COLOR_YELLOW = '#ffdc00';
    public const COLOR_ORANGE = '#ff851b';
    public const COLOR_RED = '#ff4136';
    public const COLOR_MAROON = '#85144B';
    public const COLOR_FUCHSIA = '#f012be';
    public const COLOR_PURPLE = '#b10dc9';
    public const COLOR_BLACK = '#111111';
    public const COLOR_GRAY = '#AAAAAA';

    /** @var TranslatorInterface */
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'colorpickr';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The neutral behaviour's (Base\Field\Behaviour\ColorPickr), its buttons' words in this translator's.
        $defaults = (new ColorPickr())->options([]);
        $defaults['pickr']['strings'] = [
            'save' => $this->translator->trans('@fields.color.buttons.save'),
            'clear' => $this->translator->trans('@fields.color.buttons.clear'),
            'cancel' => $this->translator->trans('@fields.color.buttons.cancel'),
        ];
        $resolver->setDefaults($defaults);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            $event->setData((new ColorPickr())->reverse($event->getData(), $options));
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $colour = new ColorPickr();
        $vars = $colour->vars($options, $view->vars['value'], $view->vars["id"]);
        $view->vars['attr'] = array_replace($view->vars['attr'], $vars['attr']);
        $view->vars['value'] = $colour->transform($view->vars['value'], $options);
    }
}
