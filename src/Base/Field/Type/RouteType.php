<?php

namespace Base\Field\Type;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\LocalizerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class RouteType extends AbstractType
{
    /** @var AdvancedRouterInterface */
    protected AdvancedRouterInterface $router;
    /** @var LocalizerInterface */
    protected LocalizerInterface $localizer;

    public function getParent(): ?string
    {
        return SelectType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'route';
    }

    public function __construct(AdvancedRouterInterface $router, LocalizerInterface $localizer)
    {
        $this->router = $router;
        $this->localizer = $localizer;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "capitalize" => null,
            'choice_loader' => function (Options $options) {
                $routeList = [];
                return ChoiceList::loader($this, new CallbackChoiceLoader(function () {
                    return array_flip(array_transforms(
                        function ($k, $r): ?array {
                            $lang = explode(".", $k);
                            $lang = end($lang);

                            $localized = in_array($lang, $this->localizer->getAvailableLocaleLangs());
                            if ($localized) {
                                if ($lang != $this->localizer->getDefaultLocaleLang()) {
                                    return null;
                                }

                                $k = str_rstrip($k, "." . $lang);
                                return [$k, "<b>Name:</b> " . strtolower($k . ".{_locale}") . "<br/><b>Path:</b> " . $r->getPath()];
                            }

                            return [$k, "<b>Name:</b> " . strtolower($k) . "<br/><b>Path:</b> " . $r->getPath()];
                        },
                        $this->router->getRouteCollection()->all()
                    ));
                }), $routeList);
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
