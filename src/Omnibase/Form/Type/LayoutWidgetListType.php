<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Omnibridge\Symfony\Omnibase\Field\Type\SelectType;
use Base\Service\WidgetProviderInterface;
use Exception;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\StringUtil;
use Traversable;

class LayoutWidgetListType extends AbstractType implements DataMapperInterface
{
    /**
     * @var WidgetProviderInterface
     */
    protected WidgetProviderInterface $widgetProvider;

    public function getBlockPrefix(): string
    {
        return "_base_" . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function __construct(WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'widgets' => [],
            'sortable' => true,
            'excluded_widgets' => [],
            'locale' => null,
            'attr' => array(
                'class' => 'needs-validation'
            )
        ]);
    }

    /**
     * @param $data
     * @param $from
     * @param $to
     * @return array
     * @throws Exception
     */
    public function getFormattedData($data, $from = ".", $to = "-")
    {
        $newData = [];
        if (!$data) {
            return [];
        } elseif (is_associative($data)) {
            foreach ($data as $name => $value) {
                $newData[str_replace($from, $to, $name)] = $value;
            }
        } elseif (is_subclass_of($data, Widget::class)) {
            $newData[str_replace($from, $to, $data->getName())] = $data->getName();
        } else {
            throw new Exception("Unexpected data provided (expecting either associative array, Setting or BaseSetting)");
        }

        return $newData;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();

            $widgetSlots = [];

            $formattedWidgets = $this->getFormattedData($options["widgets"]);
            foreach ($formattedWidgets as $formattedWidget => $widgetOptions) {
                $widgetSlot = str_replace("-", ".", $formattedWidget);
                $widgetSlots[$formattedWidget] = $this->widgetProvider->getSlot($widgetSlot, false) ?? new Slot($formattedWidget, "");
            }

            foreach ($widgetSlots as $formattedWidget => $slot) {
                // Exclude requested widgets
                $widget = str_replace("-", ".", $formattedWidget);
                if (in_array($widget, $options["excluded_widgets"])) {
                    continue;
                }

                // Set widget options
                $widgetOptions = $options["widgets"][$widget];
                $widgetOptions["attr"] = $opts["attr"] ?? [];

                $widgetOptions["data_class"] = null;
                $widgetOptions["required"] = $options["widgets"][$widget]["required"] ?? false;
                $widgetOptions["select2"] = $options["select2"] ?? [];

                $widgetOptions["class"] = Widget::class;
                $widgetOptions["choice_filter"] = $options["widgets"][$widget]["choice_filter"] ?? null;
                $widgetOptions["choice_filter"][] = "^" . Slot::class;

                // Set default label
                if (!array_key_exists("label", $widgetOptions)) {
                    $label = explode("-", $formattedWidget);
                    $widgetOptions["label"] = $slot->getLabel() ?? mb_ucwords(str_replace("_", " ", camel2snake(end($label))));
                }

                if (!array_key_exists("help", $widgetOptions)) {
                    $widgetOptions["help"] = $slot->getHelp() ?? "";
                }

                $form->add($formattedWidget, SelectType::class, $widgetOptions);
                $form->get($formattedWidget)->setData($slot->getWidget() ?? null);
            }

            if (count($options["widgets"]) > 0) {
                $form->add('valid', SubmitType::class, ["attr" => ["class" => "btn btn-primary"], "translation_domain" => "controllers", "label_format" => "admin_widgets.valid"]);
            }
        });
    }

    /**
     * @param $viewData
     * @param Traversable $forms
     * @return void
     */
    public function mapDataToForms($viewData, Traversable $forms): void
    {
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     * @throws Exception
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $children = iterator_to_array($forms);

        $newViewData = [];
        foreach ($children as $name => $child) {
            $newViewData[$name] = $child->getData();
        }

        $newViewData = $this->getFormattedData($newViewData, "-", ".");

        foreach ($newViewData as $widget => $value) {
            if ($widget == "valid") {
                continue;
            }

            $formattedWidget = str_replace(".", "-", $widget);

            $newViewData[$widget] = $children[$formattedWidget]->getData() ?? [];
            if (!is_array($newViewData[$widget])) {
                $newViewData[$widget] = [$newViewData[$widget]];
            }
        }

        unset($newViewData["valid"]);
        $viewData = $newViewData;
    }
}
