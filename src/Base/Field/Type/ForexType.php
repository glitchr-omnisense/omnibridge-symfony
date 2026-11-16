<?php

namespace Base\Field\Type;

use Base\Service\Model\SelectInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

//@TODO not ready..

class ForexType extends SelectType implements SelectInterface
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            "class" => null,

            "autocomplete" => true,
            "autocomplete_pagesize" => 200,
            "autocomplete_endpoint" => null,
            "autocomplete_endpoint_parameters" => null,
        ]);

        $resolver->setNormalizer('autocomplete_endpoint', function (Options $options, $value) {
            return $value ?? "ux_autocomplete_forex";
        });

        $resolver->setNormalizer('autocomplete_endpoint_parameters', function (Options $options, $value) {
            return $value ?? [
                //"provider" => $options["adapter"]::getName(),
                "source" => "EUR",
                "target" => "USD",

                "pageSize" => $options["autocomplete_pagesize"]
            ];
        });
    }

    public static function getIcon(string $id, int $index = -1): ?string
    {
        return null;
    }

    public static function getText(string $id): ?string
    {
//        $choices = $adapter->getChoices();
//        if( ($choicePath = array_search_recursive($id, $choices)) )
//            return $choicePath[count($choicePath)-1]; // Last but one is expected to contain "text" information
//        }

        return $id;
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
