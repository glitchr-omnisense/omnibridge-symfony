<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Service\LocalizerInterface;
use Base\Field\Behaviour\DateTimePicker;
use Base\Service\ParameterBagInterface;

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

    public function __construct(ParameterBagInterface $parameterBag, LocalizerInterface $localizer)
    {
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;
    }
    
    public function getParent(): ?string
    {
        return DateTimeType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'datetimepicker';
    }

    /**
     * The neutral behaviour's options (omnisense/omnibase's
     * Base\Field\Behaviour\DateTimePicker, the one every host reads): ICU
     * format yyyy-MM-dd HH:mm, flatpickr's settings in `datetimepicker`, merged
     * over the defaults key by key (a date-only picker keeps the page's
     * language).
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $defaults = (new DateTimePicker())->options([]);
        $defaults['datetimepicker'] = DateTimePicker::picker([], $this->localizer->getLocaleLang());
        $resolver->setDefaults($defaults);
        $resolver->setNormalizer("datetimepicker", fn (Options $options, $value) => DateTimePicker::picker($value, $this->localizer->getLocaleLang()));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, (new DateTimePicker())->vars($options, $view->vars["value"] ?? null, $view->vars["id"]));
    }
}
