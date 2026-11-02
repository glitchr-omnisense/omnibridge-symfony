<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

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
        $resolver->setDefaults([
            'pickr' => [

                // Selector or element which will be replaced with the actual color-picker.
                // Can be a HTMLElement.
                'el' => null,

                // Where the pickr-app should be added as child.
                'container' => 'body',

                // Which theme you want to use. Can be 'classic', 'monolith' or 'nano'
                'theme' => self::THEME_CLASSIC,

                // Nested scrolling is currently not supported and as this would be really sophisticated to add this
                // it's easier to set this to true which will hide pickr if the user scrolls the area behind it.
                'closeOnScroll' => false,

                // Custom class which gets added to the pcr-app. Can be used to apply custom styles.
                'appClass' => 'custom-class',

                // Don't replace 'el' Element with the pickr-button, instead use 'el' as a button.
                // If true, appendToBody will also be automatically true.
                'useAsButton' => true,

                // If true pickr won't be floating, and instead will append after the in el resolved element.
                // Setting this to true will also set showAlways to true. It's possible to hide it via .hide() anyway.
                'inline' => false,

                // If true, pickr will be repositioned automatically on page scroll or window resize.
                // Can be set to false to make custom positioning easier.
                'autoReposition' => true,

                // Defines the direction in which the knobs of hue and opacity can be moved.
                // 'v' => opacity- and hue-slider can both only moved vertically.
                // 'hv' => opacity-slider can be moved horizontally and hue-slider vertically.
                // Can be used to apply custom layouts
                'sliders' => 'v',

                // Start state. If true 'disabled' will be added to the button's classlist.
                'disabled' => false,

                // If true, the user won't be able to adjust any opacity.
                // Opacity will be locked at 1 and the opacity slider will be removed.
                // The HSVaColor object also doesn't contain an alpha, so the toString() methods just
                // print HSV, HSL, RGB, HEX, etc.
                'lockOpacity' => false,

                // Precision of output string (only effective if components.interaction.input is true)
                'outputPrecision' => 0,

                // If set to false it would directly apply the selected color on the button and preview.
                'comparison' => true,

                // Default color
                'default' => '#42445a',

                // Optional color swatches. When null, swatches are disabled.
                // Types are all those which can be produced by pickr e.g. hex(a), hsv(a), hsl(a), rgb(a), cmyk, and also CSS color names like 'magenta'.
                // Example' => swatches' => ['#F44336', '#E91E63', '#9C27B0', '#673AB7'],
                'swatches' => [
                    self::COLOR_NAVY,
                    self::COLOR_BLUE,
                    self::COLOR_AQUA,
                    self::COLOR_TEAL,
                    self::COLOR_OLIVE,
                    self::COLOR_GREEN,
                    self::COLOR_LIME,
                    self::COLOR_YELLOW,
                    self::COLOR_ORANGE,
                    self::COLOR_RED,
                    self::COLOR_MAROON,
                    self::COLOR_FUCHSIA,
                    self::COLOR_PURPLE,
                    self::COLOR_BLACK,
                    self::COLOR_GRAY,
                ],

                // Default color representation of the input/output textbox.
                // Valid options are `HEX`, `RGBA`, `HSVA`, `HSLA` and `CMYK`.
                'defaultRepresentation' => 'HEX',

                // Option to keep the color picker always visible.
                // You can still hide / show it via 'pickr.hide()' and 'pickr.show()'.
                // The save button keeps its functionality, so still fires the onSave event when clicked.
                'showAlways' => false,

                // Close pickr with a keypress.
                // Default is 'Escape'. Can be the event key or code.
                // (see' => https' =>//developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent/key)
                'closeWithKey' => 'Escape',

                // Defines the position of the color-picker.
                // Any combinations of top, left, bottom or right with one of these optional modifiers' => start, middle, end
                // Examples' => top-start / right-end
                // If clipping occurs, the color picker will automatically choose its position.
                'position' => self::POSITION_BOTTOM . '-' . self::POSITION_MIDDLE,

                // Enables the ability to change numbers in an input field with the scroll-wheel.
                // To use it set the cursor on a position where a number is and scroll, use ctrl to make steps of five
                'adjustableNumbers' => true,

                // Show or hide specific components.
                // By default only the palette (and the save button) is visible.
                'components' => [

                    // Defines if the palette itself should be visible.
                    // Will be overwritten with true if preview, opacity or hue are true
                    'palette' => true,

                    'preview' => true, // Display comparison between previous state and new color
                    'opacity' => true, // Display opacity slider
                    'hue' => true,     // Display hue slider

                    // show or hide components on the bottom interaction bar.
                    'interaction' => [
                        'hex' => true,  // Display 'input/output format as hex' button  (hexadecimal representation of the rgba value)
                        'rgba' => true, // Display 'input/output format as rgba' button (red green blue and alpha)
                        'hsla' => true, // Display 'input/output format as hsla' button (hue saturation lightness and alpha)
                        'hsva' => false, // Display 'input/output format as hsva' button (hue saturation value and alpha)
                        'cmyk' => false, // Display 'input/output format as cmyk' button (cyan mangenta yellow key )
                        'input' => true, // Display input/output textbox which shows the selected color value.
                        // the format of the input is determined by defaultRepresentation,
                        // and can be changed by the user with the buttons set by hex, rgba, hsla, etc (above).
                        'cancel' => false, // Display Cancel Button, resets the color to the previous state
                        'clear' => false, // Display Clear Button; same as cancel, but keeps the window open
                        'save' => true,  // Display Save Button,
                    ],
                ],

                // Button strings, brings the possibility to use a language other than English.
                'strings' => [
                    'save' => $this->translator->trans('@fields.color.buttons.save'),
                    'clear' => $this->translator->trans('@fields.color.buttons.clear'),
                    'cancel' => $this->translator->trans('@fields.color.buttons.cancel'),
                ],
            ],
            "webpack_entry" => "form.color",

            'is_nullable' => true
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            if ($event->getData() == "#00000000" && $options["is_nullable"]) {
                $event->setData(null);
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $options["pickr"]["el"] = "#" . $view->vars["id"];

        // JSColor requirement
        $view->vars['attr']['data-color-pickr'] = json_encode($options["pickr"]);

        // JSColor class for stylsheet
        $view->vars['attr']["class"] = "form-color";

        // Add alpha channel by default
        switch (strlen($view->vars['value'])) {
            case 4:
                $view->vars['value'] .= "F";
                break;
            case 7:
                $view->vars['value'] .= "FF";
                break;
            case 9:
                break;
            default:
                $view->vars['value'] = "#00000000";
        }
    }
}
