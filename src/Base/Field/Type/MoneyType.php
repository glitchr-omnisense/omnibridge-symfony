<?php

namespace Base\Field\Type;

use AsyncAws\Core\Exception\LogicException;
use Base\Service\TradingInterface;
use Exception;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoneyType extends \Symfony\Component\Form\Extension\Core\Type\MoneyType
{
    public const CODE_ONLY = 0;
    public const LABEL_ONLY = 1;
    public const LABELCODE_ONLY = 2;

    /**
     * @var TradingInterface
     */
    protected TradingInterface $tradingMarket;

    public function __construct(TradingInterface $tradingMarket)
    {
        $this->tradingMarket = $tradingMarket;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            "currency_target" => null,
            "currency_label" => self::LABEL_ONLY,
            "currency_exchange" => null,
            'currency_list' => null,
            "use_swap" => false,
            "divisor" => 100
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $targetPath = $options["currency_target"] ? explode(".", $options["currency_target"]) : "";
        $view->vars['currency_target'] = $targetPath;

        // Check if child exists.. this just trigger an exception..
        if ($options["currency_target"]) {
            $target = $form->getParent();
            foreach ($targetPath as $path) {
                if (!$target->has($path)) {
                    throw new Exception("Cannot determine currency.. Child path \"$path\" doesn't exists in \"" . get_class($target->getViewData()) . "\".");
                }

                $target = $target->get($path);
                $targetType = $target->getConfig()->getType()->getInnerType();

                if ($targetType instanceof TranslationType) {
                    $availableLocales = array_keys($target->all());
                    $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0]);
                    $target = $target->get($locale);
                }
            }
        }

        // Build view and other options
        parent::buildView($view, $form, $options);
        $view->vars["scale"] = $options["scale"];

        $view->vars["currency"] = $options["currency"];

        if ($options["currency_list"] === null) {
            $options["currency_list"] = array_keys($this->tradingMarket->getFallback($options["currency"]));
        }

        $view->vars["currency_list"] = array_values(array_unique(array_merge([$options["currency"]], $options["currency_list"] ?? [])));
        foreach ($view->vars["currency_list"] as $currency) {
            if (!Currencies::exists($view->vars["currency"])) {
                throw new LogicException("Currency \"\" not referenced in \"" . Currencies::class . "\"");
            }
        }

        $view->vars["currency_exchange"] = $options["currency_exchange"];
        if ($view->vars["currency_exchange"] === null) {
            $view->vars["currency_exchange"] = [];
            foreach ($view->vars["currency_list"] as $currency) {
                if ($view->vars["currency"] == $currency) {
                    $view->vars["currency_exchange"][$currency] = 1.0;
                    continue;
                }

                $exchangeRate = $this->tradingMarket->get($view->vars["currency"], $currency, ["use_swap" => $options["use_swap"]]);
                if (!$exchangeRate) {
                    continue;
                }

                $view->vars["currency_exchange"][$currency] = $exchangeRate->getValue();
            }
        }

        $view->vars["currency_list"] = array_values(array_intersect($view->vars["currency_list"], array_keys($view->vars["currency_exchange"])));

        switch ($options["currency_label"]) {
            case self::CODE_ONLY:
                $view->vars["currency_label"] = array_combine($view->vars["currency_list"], $view->vars["currency_list"]);
                break;

            default:
            case self::LABEL_ONLY:
                $view->vars["currency_label"] = array_combine(
                    $view->vars["currency_list"],
                    array_map(fn($c) => Currencies::getSymbol($c), $view->vars["currency_list"])
                );

                break;

            case self::LABELCODE_ONLY:
                $view->vars["currency_label"] = array_combine(
                    $view->vars["currency_list"],
                    array_map(fn($c) => Currencies::getSymbol($c) . " (" . $c . ")", $view->vars["currency_list"])
                );
                break;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options); // TODO: Change the autogenerated stub
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            $form = $event->getForm();
            $event->setData(str_replace([" ", ","], ["", "."], $event->getData()));
        });
    }
}
