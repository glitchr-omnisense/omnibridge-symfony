<?php

namespace Base\Field\Type;

use Base\Service\Model\SelectInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

//https://codepen.io/peiche/pen/mRBGmR
//https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.yml

class IconType extends SelectType implements SelectInterface
{
    public static mixed $iconProvider = null;

    public function __construct(...$args)
    {
        self::$iconProvider = array_pop($args);
        parent::__construct(...$args);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            "class" => null,
            "adapter" => $this->parameterBag->get("base.icon_provider.default_adapter"),

            "autocomplete" => true,
            "autocomplete_pagesize" => 200,
            "autocomplete_endpoint" => null,
            "autocomplete_endpoint_parameters" => null,
        ]);

        $resolver->setNormalizer('autocomplete_endpoint', function (Options $options, $value) {
            return $value ?? "ux_autocomplete_icons";
        });

        $resolver->setNormalizer('autocomplete_endpoint_parameters', function (Options $options, $value) {
            return $value ?? [
                "provider" => $options["adapter"]::getName(),
                "pageSize" => $options["autocomplete_pagesize"]
            ];
        });
    }

    public static function getIcon(string $id, int $index = -1): ?string
    {
        $adapter = self::$iconProvider->getAdapter($id);
        return $adapter ? $id : null;
    }

    public static function getText(string $id): ?string
    {
        $adapter = self::$iconProvider->getAdapter($id);
        if ($adapter) {
            $choices = $adapter->getChoices();
            if (($choicePath = array_search_recursive($id, $choices))) {
                return $choicePath[count($choicePath) - 1];
            } // Last but one is expected to contain "text" information
        }

        return null;
    }

    public static function getHtml(string $id): ?string
    {
        return null;
    }

    public static function getData(string $id): ?array
    {
        return [];
    }
}
