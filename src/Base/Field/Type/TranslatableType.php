<?php

namespace Base\Field\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;

class TranslatableType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_field_name' => "translations",
            'translation_class' => null,

            'autoload' => true,
            'fields' => [],
            'excluded_fields' => [],

            "single_locale" => null,
            "default_locale" => null,
            "required_locales" => null,
            "available_locales" => null,

            // Implement this if you want to be able to handle
            // multiple translatable entities as a collection
            "multiple" => false
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translationOptions = [];

        if ($options["fields"]) {
            $translationOptions['fields'] = $options["fields"];
        }
        if ($options["autoload"]) {
            $translationOptions['autoload'] = $options["autoload"];
        }
        if ($options["excluded_fields"]) {
            $translationOptions['excluded_fields'] = $options["excluded_fields"];
        }

        if ($options["single_locale"]) {
            $translationOptions['single_locale'] = $options["fields"];
        }
        if ($options["default_locale"]) {
            $translationOptions['default_locale'] = $options["default_locale"];
        }
        if ($options["required_locales"]) {
            $translationOptions['required_locales'] = $options["required_locales"];
        }
        if ($options["available_locales"]) {
            $translationOptions['available_locales'] = $options["available_locales"];
        }

        $builder->add($options["translation_field_name"], TranslationType::class, $translationOptions);
    }
}
