<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Twig\Environment;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class DateTimePickerType extends AbstractType
{
    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;

    /** @var LocalizerInterface */
    protected LocalizerInterface $localizer;

    /** @var Environment */
    protected Environment $twig;

    public function __construct(ParameterBagInterface $parameterBag, Environment $twig, LocalizerInterface $localizer)
    {
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;
        $this->twig = $twig;
    }
    
    public function getParent(): ?string
    {
        return DateTimeType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'datetimepicker';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([

            // PHP Datetime format:
            // This format is replacing the shitty HTML5_FORMAT :-)
            "format" => "yyyy-MM-dd HH:mm",
            "html5" => false,
            "widget" => "single_text",
            "required" => false,
            "webpack_entry" => "form.datetime",

            "debug" => false,
            "datetimepicker" => [
                "enableTime" => true,
                "locale" => $this->localizer->getLocaleLang(),
                "dateFormat" => "Y-m-d H:i", // Format must match... between format option and dateFormat (JS Format)
            ]
        ]);

        // Merge whatever the caller passed OVER the defaults instead of
        // replacing them. A caller that wants a date-only picker naturally
        // writes ["enableTime" => false, "dateFormat" => "Y-m-d"], and with a
        // plain default that silently dropped `locale` - leaving flatpickr in
        // English on a French form. Only the keys actually given are
        // overridden now, so a partial option stays partial.
        $resolver->setNormalizer("datetimepicker", function (Options $options, $value) {
            return array_merge([
                "enableTime" => true,
                "locale" => $this->localizer->getLocaleLang(),
                "dateFormat" => "Y-m-d H:i",
            ], is_array($value) ? $value : []);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //
        // Datetime picker Options
        $dateTimePickerOpts = $options["datetimepicker"];

        $view->vars["datetimepicker"] = json_encode($dateTimePickerOpts);
    }
}
