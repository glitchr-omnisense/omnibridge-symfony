<?php

namespace Base\Field\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CurrencyType extends AbstractType
{
    public const DISPLAY_SYMBOL = "symbol";
    public const DISPLAY_CODE = "code";

    public function getParent(): ?string
    {
        return SelectType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'currency';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'display_symbol' => true,
            'display_code' => true,
            'choice_loader' => function (Options $options) {
                if (!class_exists(Intl::class)) {
                    throw new LogicException(sprintf('The "symfony/intl" component is required to use "%s". Try running "composer require symfony/intl".', static::class));
                }

                $choiceTranslationLocale = $options['choice_translation_locale'];

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($options, $choiceTranslationLocale) {
                    return array_transforms(
                        fn($name, $code): ?array => [
                            trim(
                                mb_ucwords($name) .
                                ($options["display_code"] && Currencies::getSymbol($code) != $code ? " / " . $code : null) .
                                ($options["display_symbol"] ? " / " . Currencies::getSymbol($code) : null)
                            ), $code],
                        array_flip(Currencies::getNames($choiceTranslationLocale))
                    );
                }), $choiceTranslationLocale);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a valid currency.';
            },
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
    }
}
