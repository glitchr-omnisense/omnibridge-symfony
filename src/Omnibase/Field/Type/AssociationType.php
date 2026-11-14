<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Base\Field\Behaviour\Association;
use Omnibridge\Symfony\Omnibase\Form\FormFactory;
use Omnibridge\Symfony\Omnibase\Form\FormMapping;
use Omnibridge\Symfony\Omnibase\Form\FormTypes;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\EntityCache;
use Base\Service\Model\Autocomplete;
use Base\Service\TranslatorInterface;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use RuntimeException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Traversable;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class AssociationType extends AbstractType implements DataMapperInterface
{
    use BaseTrait;

    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    /**
     * @var FormFactory|null
     */
    protected ?FormFactory $formFactory = null;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var Autocomplete
     */
    protected Autocomplete $autocomplete;

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    /**
     * @var EntityHydrator
     */
    protected $entityHydrator;

    public function getBlockPrefix(): string
    {
        return 'association';
    }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, EntityHydrator $entityHydrator, TranslatorInterface $translator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityHydrator = $entityHydrator;
        $this->translator = $translator;
        $this->formFactory = $formFactory;

        $this->autocomplete = new Autocomplete($this->translator);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // omnisense/omnibase's Association: the defaults and what the normalizers make of them, the same on every host.
        $resolver->setDefaults(array_replace(Association::DEFAULTS, [
            'entry_label' => function ($i, $label) {
                if ($i === "__prototype__") {
                    return false;
                }

                if (!is_object($label)) {
                    return $this->translator->trans("@fields.collection.entry") . " #" . (((int)$i) + 1);
                }

                $_label = $this->translator->transEntity($label) . " #" . (((int)$i) + 1);
                if (is_stringeable($label)) {
                    $_label .= " : " . ((string) $label);
                }
                return $_label;
            },
        ]));

        // A to-many association is never required (no entries is a legitimate value for one: a Count constraint
        // says why it refuses); entry_required governs the single-valued case. See Association::options().
        $resolver->setNormalizer('required', fn (Options $options, $value) => Association::options(['multiple' => $options['multiple'], 'entry_required' => $options['entry_required']])['required']);
        $resolver->setNormalizer('data_class', fn (Options $options, $value) => $options["multiple"] ? null : ($value ?? null));
        $resolver->setNormalizer('allow_add', fn (Options $options, $value) => Association::options(['group' => $options['group'], 'allow_add' => $value])['allow_add']);
        $resolver->setNormalizer('allow_delete', fn (Options $options, $value) => Association::options(['group' => $options['group'], 'allow_delete' => $value])['allow_delete']);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['href'] = $options["href"];
        $view->vars['html'] = $options["html"];
        $view->vars["multiple"] = $options["multiple"];
        $view->vars["group"] = $options["group"];
        $view->vars["row_group"] = $options["row_group"];
        $view->vars["allow_add"] = $options["allow_add"];
        $view->vars["allow_delete"] = $options["allow_delete"];
        $view->vars["keep_indexes"] = $options["keep_indexes"];
        $view->vars['length'] = $options["length"];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            $options["class"] = $this->formFactory->guessClass($event, $options);

            $length = $options["group"] ? $options["length"] : max(1, $options["length"]);
            $options["multiple"] = $this->formFactory->guessMultiple($form, $options);

            if ($options["multiple"]) {
                $dataClass = $options["class"];
                unset($options["class"]);

                if (!is_array($data) && !$data instanceof Collection) {
                    $data = [$data];
                }

                // What omnibase's Association gives the collection of several records' forms, its entries this type.
                $collectionOptions = Association::collection($options + ['class' => $dataClass]);
                $collectionOptions['entry_type'] = AssociationType::class;
                $collectionOptions['entry_label'] = $options["entry_label"];

                $form->add("_collection", CollectionType::class, $collectionOptions);
            } else {
                $dataClass = $options["class"] ?? $this->formFactory->guessClass($event, $options);
                if (!$dataClass) {
                    throw new RuntimeException(
                        'Unable to get "class" or compute "data_class" from form "' . $form->getName() . '" or any of its parents. ' .
                        'Please define "class" option in the main AssociationType you defined or make sure there is a way to guess the expected output information'
                    );
                }

                $fields = FormMapping::of($this->classMetadataManipulator)->fields($dataClass, $options["fields"], $options["excluded_fields"]);
                if (!$options["autoload"]) {
                    $fields = array_filter($fields, fn($k) => array_key_exists($k, $options["fields"]), ARRAY_FILTER_USE_KEY);
                }

                foreach ($fields as $fieldName => $field) {
                    if ($options["recursive"] && array_key_exists($form->getName(), $field)) {
                        $field = $field[$form->getName()];
                    }

                    $fieldType = $field['form_type'] ?? null;
                    unset($field['form_type']);

                    $isNullable = FormMapping::of($this->classMetadataManipulator)->nullable($dataClass, $fieldName);
                    if (!array_key_exists("required", $field) && $isNullable) {
                        $field['required'] = !$isNullable;
                    }

                    $fieldEntity = $field['allow_entity'] ?? $options["allow_entity"] ?? false;
                    unset($field['allow_entity']);

                    if ($fieldType !== null && $fieldEntity && !FormTypes::same($fieldType, AssociationType::class)) {
                        $form->add($fieldName, $fieldType, $field);
                    }
                }

                if ($options["keep_indexes"]) {
                    $form->add("_index", HiddenType::class, ["mapped" => false, "required" => false]);
                }
            }
        });
    }

    /**
     * @param $viewData
     * @param Traversable $forms
     * @return void
     * @throws Exception
     */
    public function mapDataToForms($viewData, Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        $data = $viewData;

        if ($data instanceof Collection) {
            $form = current(iterator_to_array($forms));
            $form->setData($data);
        } elseif (is_object($entity = $data)) {
            $childForms = iterator_to_array($forms);
            foreach ($childForms as $fieldName => $childForm) {
                if (!$childForm->getConfig()->getOption("mapped")) {
                    continue;
                }

                $value = $this->propertyAccessor->getValue($entity, $fieldName);
                if (empty($value)) {
                    $value = null;
                }

                $childFormType = get_class($childForm->getConfig()->getType()->getInnerType());

                if (is_instanceof($childFormType, ArrayType::class)) {
                    if (is_serialized($value)) {
                        $value = unserialize($value);
                    } else {
                        $value = $value !== null && !is_array($value) ? [$value] : $value;
                    }
                }

                if (is_instanceof($childFormType, NumberType::class)) {
                    $value = floatval($value);
                }

                if (is_instanceof($childFormType, BooleanType::class)) {
                    $value = boolval($value);
                }

                if (is_instanceof($childFormType, PercentType::class)) {
                    $value = intval($value);
                }
                if (is_instanceof($childFormType, IntegerType::class)) {
                    $value = intval($value);
                }

                if (is_instanceof($childFormType, CollectionType::class)) {
                    $value ??= [];
                    if ($value instanceof Collection) {
                        $value = $value->toArray();
                    }

                    if (!is_array($value)) {
                        $value = [$value];
                    }
                }

                $childForm->setData($value);
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
        $form = current(iterator_to_array($forms));
        $formParent = $form->getParent();
        if (FormMapping::isPersisted($formParent?->getData()) &&
            FormMapping::of($this->classMetadataManipulator)->isCollectionOwner($formParent, $formParent?->getData()) === false) {
            return;
        }

        $options = $formParent->getConfig()->getOptions();
        $options["class"] = $options["class"] ?? $this->formFactory->guessClass($formParent, $options);
        $options["multiple"] = $options["multiple"] ?? $this->formFactory->guessMultiple($formParent, $options);
        $options["allow_null"] = $options["allow_null"] ?? $this->formFactory->guessNullable($formParent, $options);

        $entries = new ArrayCollection();
        foreach (iterator_to_array($forms) as $fieldName => $childForm) {
            $entries[$fieldName] = $childForm->getData();
        }

        if (!$options["multiple"] && $this->classMetadataManipulator->isEntity($options["class"])) {
            if (null === $this->classMetadataManipulator->getShape($options["class"])) {
                throw new Exception("Entity \"" . $options["class"] . "\" not found.");
            }

            foreach ($entries as $fieldName => $entry) {
                $formType = $options["fields"][$fieldName]["form_type"] ?? null;
                if (FormTypes::same($formType, CollectionType::class)) {
                    unset($entries[$fieldName]);
                }
            }

            $aggregateModel = EntityHydrator::CLASS_METHODS;
            if (!$options["allow_null"]) {
                $aggregateModel |= EntityHydrator::IGNORE_NULLS;
            }

            $viewData = $this->entityHydrator->hydrate(
                is_object($viewData) ? $viewData : $options["class"],
                $entries instanceof Collection ? $entries->toArray() : $entries,
                [],
                $aggregateModel
            );

        } elseif (null !== $collection = FormMapping::of($this->classMetadataManipulator)->collection($viewData, $formParent)) {

            // A collection the persistence read: its owner and sides from the shapes (FormMapping), whichever persistence.
            $mappedBy = $collection->mappedBy;
            if ($entries->containsKey("_collection")) {
                $entries = $entries->get("_collection");
            }

            $oldData = $viewData->toArray();
            if ($collection->isInverseSide()) {

                foreach (array_diff_object($oldData, $entries->toArray()) as $entry) {
                    $owningSide = $this->propertyAccessor->getValue($entry, $mappedBy);
                    if (!$owningSide instanceof Collection) {
                        $this->propertyAccessor->setValue($entry, $mappedBy, null);
                    } elseif ($owningSide->contains($collection->owner)) {
                        $owningSide->removeElement($collection->owner);
                    }
                }
            }

            $this->evict($viewData, $collection, array_unique_object(array_union($oldData, $entries->toArray())));

            $viewData->clear();

            foreach ($entries as $entry) {

                $viewData->add($entry);
                if ($collection->isInverseSide()) {

                    $owningSide = $this->propertyAccessor->getValue($entry, $mappedBy);
                    if (!$owningSide instanceof Collection) {
                        $this->propertyAccessor->setValue($entry, $mappedBy, $collection->owner);
                    } elseif (!$owningSide->contains($collection->owner)) {
                        $owningSide->add($collection->owner);
                    }
                }
            }

        } elseif ($options["multiple"]) {
            $viewData = new ArrayCollection();
            foreach (iterator_to_array($forms) as $fieldName => $childForm) {
                foreach ($childForm as $key => $value) {
                    $viewData[$key] = $value->getViewData();
                }
            }
        } else {
            $viewData = current(iterator_to_array($forms))->getViewData();
        }
    }

    /**
     * The entries' cached rows forgotten where the persistence keeps a cache of them (Base\Database\Backend\EntityCache:
     * Doctrine's second-level cache; none on omnibridge/eloquent), and on Doctrine its cached collections too, which only
     * Doctrine has.
     *
     * @param list<object> $entries
     */
    private function evict(mixed $viewData, \Omnibridge\Symfony\Omnibase\Form\CollectionMapping $collection, array $entries): void
    {
        $cache = Backends::of($this->entityHydrator->getUnitOfWork(), EntityCache::class);
        if (!$cache->enabled()) {
            return;
        }

        $regions = $viewData instanceof \Doctrine\ORM\PersistentCollection && method_exists($this->classMetadataManipulator, 'getEntityManager')
            ? $this->classMetadataManipulator->getEntityManager()->getCache()
            : null;
        foreach ($entries as $data) {
            $cache->evict(get_class($data), $data->getId());
            if ($collection->owning) {
                $regions?->evictCollection(get_class($data), $collection->inversedBy, $data->getId());
            }
            if (!$collection->owning && $collection->mappedBy) {
                $regions?->evictCollection($collection->target, $collection->mappedBy, $collection->owner);
            }
        }
    }
}
