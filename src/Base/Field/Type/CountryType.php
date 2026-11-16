<?php

namespace Base\Field\Type;

use Base\Entity\User;
use Base\Service\Model\SelectInterface;
use Base\Field\Choice\Countries;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountryType extends SelectType implements SelectInterface
{
    /** @see Countries::addCountry() */
    public static function addCountry($code, $country)
    {
        Countries::addCountry($code, $country);
    }

    /** @see Countries::addCountries() */
    public static function addCountries($array)
    {
        Countries::addCountries($array);
    }

    public static function getName(string $code)
    {
        return Countries::getName($code);
    }

    public static function getNamesWithoutAddons()
    {
        return Countries::getNamesWithoutAddons();
    }

    public static function getNames($addons = false)
    {
        return Countries::getNames($addons);
    }

    public static function getChoices(): array
    {
        return Countries::getChoices();
    }

    public static function getIcon(string $id, int $index = -1): ?string
    {
        return null;
    }

    public static function getText(string $id): ?string
    {
        return self::getName($id);
    }

    public static function getHtml(string $id): ?string
    {
        return "<img class='country-flag' src='/bundles/base/images/flags/" . $id . ".svg' alt='" . $id . "'> " . self::getName($id) . "</>";
    }

    public static function getData(string $id): ?array
    {
        return [];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'choices' => $this->getChoices(),
            'alpha3' => false,
            "use_html" => true,
            "empty_data" => User::getCookie("country") ?? null
        ]);
    }
}
