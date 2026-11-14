<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Form\FormFactory;
use Omnibridge\Symfony\Omnibase\Form\FormMapping;

use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use InvalidArgumentException;
use Base\Entity\Layout\Attribute as LayoutAttribute;
use Base\Field\Behaviour\Attribute;
use Base\Entity\Layout\Attribute\Common\AbstractRule;
use Base\Entity\Layout\Attribute\Common\AbstractAction;
use Base\Entity\Layout\Attribute\Common\AbstractScope;

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Base\Database\Entity\Extension\TranslatableInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\DataMapperInterface;
use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Form\FormBuilderInterface;
use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractScopeAdapter;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Traversable;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Omnibridge\Symfony\Omnibase\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class AttributeType extends AbstractType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    /**
     * @var FormFactory|null
     */
    protected ?FormFactory $formFactory = null;

    /**
     * @var PropertyAccessorInterface|null
     */
    protected ?PropertyAccessorInterface $propertyAccessor = null;

    public function getBlockPrefix(): string
    {
        return 'attribute';
    }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // omnisense/omnibase's Attribute: the defaults, the same on every host.
        $resolver->setDefaults(Attribute::DEFAULTS);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            if ($value !== null && !class_exists($value)) {
                throw new InvalidArgumentException("\"class\" option is \"" . $value . "\", but the class itself doesn't exists");
            }
            if ($value !== null && !class_exists($value, AbstractAttribute::class)) {
                throw new InvalidArgumentException("\"class\" option is \"" . $value . "\", but the class itself doesn't inherit from \"" . AbstractAttribute::class . "\"");
            }

            return $value;
        });

        $resolver->setNormalizer('abstract_class', function (Options $options, $value) {
            if ($value !== null && !class_exists($value)) {
                throw new InvalidArgumentException("\"abstract_class\" option is \"" . $value . "\", but the class itself doesn't exists");
            }
            if ($value !== null && !is_instanceof($value, AbstractAdapter::class)) {
                throw new InvalidArgumentException("\"abstract_class\" option is \"" . $value . "\", but doesn't inherit from \"" . AbstractAdapter::class . "\"");
            }

            return $value;
        });

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if ($options["multiple"]) {
                return null;
            }
            return $value ?? null;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["multiple"] = $this->formFactory->guessMultiple($form, $options);
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["allow_add"] = $options["allow_add"];
        $view->vars["is_inherited"] = $view->children["choice"]->vars["is_inherited"] ?? false;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            // The attribute's class and its adapters' (omnibase's Attribute::classes()).
            $options = array_replace($options, Attribute::classes($options, $options["class"] ?? $this->formFactory->guessClass($event, $options)));

            $options["multiple"] = $this->formFactory->guessMultiple($event, $options);
            $options["sortable"] = $this->formFactory->guessSortable($event, $options);

            $form = $event->getForm();
            $parentData = $form->getParent()?->getData();
            // Its select of adapters, as omnibase's Attribute::choice() gives it.
            $form->add("choice", SelectType::class, Attribute::choice($options, $parentData));

            $data = $event->getData() ?? [];
            $event->setData($data);

            if ($data !== null) {
                if ($data instanceof Collection) {
                    $data = $data->toArray();
                } elseif (!is_array($data)) {
                    $data = [$data];
                }

                if (!$options["multiple"]) {
                    $data = array_slice($data, 0, 1);
                }

                //
                // First process universal data..
                $unvFields = array_transforms(fn($k, $v): ?array => !class_implements_interface($v, TranslatableInterface::class) ? [Attribute::key($v),
                    array_merge(
                        $v->getAdapter()->getOptions(),
                        [
                            "label" => $v->getAdapter()->getLabel(),
                            "help" => $v->getAdapter()->getHelp(),
                            "required" => false,

                            "form_type" => $v->getAdapter()::getType(),]
                    )
                ] : null, $data);

                if (!empty($unvFields)) {
                    $unvData = array_transforms(fn($k, $v): ?array => !class_implements_interface($v, TranslatableInterface::class) ? [Attribute::key($v), $v->get() ?? ""] : null, $data);
                    foreach ($unvFields as $code => $field) {
                        $form->add($code, AssociationType::class, [
                            "label" => false,
                            "group" => false,
                            "row_group" => false,
                            "autoload" => false,
                            "fields" => ["value" => $field],
                            "class" => $options["class"],
                        ]);

                        $form->get($code)->setData(100);
                    }
                }

                //
                // Then process translatable data
                $intlFields = array_transforms(fn($k, $v): ?array => class_implements_interface($v, TranslatableInterface::class) ? [Attribute::key($v), array_merge($v->getAdapter()->getOptions(), [
                    "label" => $v->getAdapter()->getLabel(),
                    "help" => $v->getAdapter()->getHelp(),
                    "required" => false,
                    "form_type" => $v->getAdapter()::getType()])
                ] : null, $data);

                if (!empty($intlFields)) {
                    $intlData = array_transforms(fn($k, $v): ?array => class_implements_interface($v, TranslatableInterface::class) ? [Attribute::key($v), $v->getTranslations()] : null, $data);

                    $form->add("intl", TranslationType::class, [
                        "translatable_class" => $options["class"],
                        "multiple" => true,
                        "autoload" => false,
                        "fields" => ["value" => $intlFields]
                    ]);

                    $form->get("intl")->setData($intlData);
                }
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
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        $forms = iterator_to_array($forms);
        $choiceForm = $forms["choice"];

        if (FormMapping::isPersisted($viewData)) {
            $choiceForm->setData($viewData->map(fn($e) => $e->getAdapter()));
            foreach ($viewData as $attribute) {
                $key = array_search_user($forms, fn(string $k, $_) => str_starts_with($k, $attribute->getAdapter()->getCode()));
                if ($key !== false) {
                    $forms[$key]->setData($attribute);
                    array_key_removes($forms, $key);
                }
            }
        } elseif ($viewData instanceof LayoutAttribute) {
            $choiceForm->setData($viewData->getAdapter());
            $key = array_search_user($forms, fn(string $k, $_) => str_starts_with($k, $viewData->getAdapter()->getCode()));
            if ($key !== false) {
                $forms[$key]->setData($viewData);
                array_key_removes($forms, $key);
            }
        }
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     * @throws MappingException
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $form = iterator_to_array($forms)["choice"]->getParent();
        $options = $form->getConfig()->getOptions();
        $options["class"] = $attributeClass = $options["class"] ?? $this->formFactory->guessClass($form, $options) ?? AbstractAttribute::class;
        $options["multiple"] = $options["multiple"] ?? $this->formFactory->guessMultiple($form, $options);

        $choiceForm = iterator_to_array($forms)["choice"];
        $choiceMultiple = $choiceForm->getConfig()->getOption("multiple") ?? false;
        $choiceData = $choiceForm->getData();

        if ($choiceMultiple != $options["multiple"]) {
            throw new Exception("Unexpected mismatching between choices and attributes");
        }

        if (!$choiceMultiple) {
            $viewData = new ($attributeClass)($choiceData);
        } else {
            $viewData ??= new ArrayCollection();
            if (is_array($viewData)) {
                $viewData = new ArrayCollection($viewData);
            }

            $bakData = is_object($viewData) ? clone $viewData : null;
            $viewData->clear();

            foreach ($choiceData as $choice) {
                if (($attribute = $bakData->filter(fn(AbstractAttribute $e) => $e->getAdapter() === $choice)->first())) {
                    $key = array_search_user(iterator_to_array($forms), fn(string $k, $_) => str_starts_with($k, $attribute->getAdapter()->getCode()));
                    if (($attributeForm = iterator_to_array($forms)[$key] ?? null)) {
                        $data = $attributeForm->getData();
                        $attribute->set($data ? $data->get() : null);
                    }

                    $viewData->add($attribute);
                    $bakData->removeElement($attribute);
                } elseif ($choice instanceof AbstractAdapter) {
                    $viewData->add(new ($attributeClass)($choice));
                } else {
                    throw new InvalidArgumentException("Invalid argument passed to attribute choice, expected class inheriting form " . AbstractAdapter::class);
                }
            }

            foreach ($bakData as $data) {
                if ($data instanceof TranslatableInterface) {
                    $data->clearTranslations();
                }
            }

            // A collection the persistence read, on its inverse side: each entry's side set to its owner (FormMapping).
            $collection = FormMapping::of($this->classMetadataManipulator)->collection($viewData, ($first = current(iterator_to_array($forms))) ? $first->getParent() : null);
            if (null !== $collection && $collection->isInverseSide()) {

                $mappedBy = $collection->mappedBy;
                foreach ($viewData as $entry) {
                    $this->propertyAccessor->setValue($entry, $mappedBy, $collection->owner);
                }
            }
        }
    }
}
