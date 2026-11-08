<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator;
use Omnibridge\Symfony\Omnibase\Field\Type\AssociationType;
use Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType;
use Omnibridge\Symfony\Omnibase\Field\Type\SelectType;
use Omnibridge\Symfony\Omnibase\Field\Type\TranslationType;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded (plan "omnibase agnostic", step B3).
class_exists(MetadataManipulator::class);

/**
 * What the form and field types read of an entity's mapping, on the persistence's shapes (omnisense/omnibase's plan
 * "omnibase agnostic", step B leftovers): the form fields a class's mapping suggests, whether a property is nullable,
 * an association's order, a collection's owner and sides, whether a collection's entity owns it. omnibridge/doctrine's
 * ClassMetadataManipulator answered these from Doctrine's ClassMetadata (getFields(), getMapping(), getFieldMapping(),
 * isCollectionOwner(), a PersistentCollection's getMapping()); here they are asked of the manipulator's neutral methods
 * only - getShape(), getFieldName(), getTypeOfField()... -, whichever persistence answers them, with the same answers
 * (the core's FormMappingTest compares them for every class Doctrine maps).
 *
 * A collection a persistence read (Doctrine's PersistentCollection, omnibridge/eloquent's LazyCollection: both
 * doctrine/collections' AbstractLazyCollection) is a persisted one; its owner and property are the collection's own
 * where it knows them (Doctrine's), else the form's that holds it (its parent's data, its property path).
 */
final class FormMapping
{
    /** @var \WeakMap<MetadataManipulator, self>|null */
    private static ?\WeakMap $instances = null;

    /** @param list<string> $globalExcludedFields what getFields() never suggests (ClassMetadataManipulator's default) */
    public function __construct(private readonly MetadataManipulator $manipulator, private readonly array $globalExcludedFields = ['id', 'translatable', 'locale'])
    {
    }

    /** The one of a manipulator. */
    public static function of(MetadataManipulator $manipulator): self
    {
        self::$instances ??= new \WeakMap();

        return self::$instances[$manipulator] ??= new self($manipulator);
    }

    /** Whether a collection is one a persistence read (and so has an owner), not one the application made. */
    public static function isPersisted(mixed $collection): bool
    {
        return $collection instanceof AbstractLazyCollection;
    }

    /**
     * The form fields an entity class's mapping suggests (its fields, the inverse sides of its associations), with
     * those asked laid over: ClassMetadataManipulator::getFields()'s answer.
     *
     * @param array<string, mixed> $fields
     * @param list<string>         $excludedFields
     *
     * @return array<string, mixed>
     */
    public function fields(null|string|object $entityOrClass, array $fields = [], array $excludedFields = []): array
    {
        if (!empty($fields) && !is_associative($fields)) {
            throw new \Exception("Associative array expected for 'fields' parameter, '".\gettype($fields)."' received");
        }

        $fieldKeys = array_keys($fields);

        $shape = $this->manipulator->getShape($entityOrClass) ?? throw new \InvalidArgumentException("Entity expected, '".(\is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass)."' is not an entity.");
        $className = $shape->className();
        $validFields = array_fill_keys($shape->fieldNames(), []);
        $validFields += $this->associationFormTypes($shape);

        // Auto detect some fields..
        foreach ($validFields as $fieldName => $field) {
            if (str_starts_with($fieldName, '_')) {
                continue;
            }
            $type = $this->manipulator->getTypeOfField($entityOrClass, $fieldName);
            if ('id' == $fieldName) {
                $validFields[$fieldName] = ['form_type' => HiddenType::class];
            } elseif ('uuid' == $fieldName) {
                $validFields[$fieldName] = ['form_type' => HiddenType::class];
            } elseif ('translations' == $fieldName) {
                $validFields[$fieldName] = [
                    'form_type' => TranslationType::class,
                    'translatable_class' => $className,
                    'translation_class' => $className::getTranslationEntityClass(),
                ];
            } elseif ($shape->hasField($fieldName) && null !== ($enumClass = $this->manipulator->getEnumClass($entityOrClass, $fieldName))) {
                // A PHP enum is chosen in a list of its cases, whatever the column that stores it.
                $validFields[$fieldName] = ['form_type' => SelectType::class, 'class' => $enumClass];
            } elseif ('datetime' == $type) {
                $validFields[$fieldName] = ['form_type' => DateTimePickerType::class];
            } elseif ('array' == $type || 'json' == $type) {
                $validFields[$fieldName] = ['form_type' => SelectType::class, 'tags' => true];
            } elseif ('integer' == $type) {
                $validFields[$fieldName] = ['form_type' => NumberType::class];
            } elseif ('string' == $type) {
                $validFields[$fieldName] = ['form_type' => TextType::class];
            } elseif ('text' == $type) {
                $validFields[$fieldName] = ['form_type' => TextareaType::class];
            } elseif (($enumType = $this->manipulator->getDoctrineType($type)) instanceof EnumType) {
                $validFields[$fieldName] = ['form_type' => SelectType::class, 'class' => \get_class($enumType)];
            } elseif (($setType = $this->manipulator->getDoctrineType($type)) instanceof SetType) {
                $validFields[$fieldName] = ['form_type' => SelectType::class, 'class' => \get_class($setType)];
            }
        }

        $fields = array_map(fn ($f) => \is_array($f) ? $f : ['form_type' => $f], $fields);
        foreach ($fields as $fieldName => $field) {
            if (\is_array($field) && !empty($field)) {
                $fields[$fieldName]['form_type'] = $field['form_type'] ?? $validFields[$fieldName]['form_type'] ?? null;

                $validFields[$fieldName] = $fields[$fieldName] ?? $validFields[$fieldName] ?? [];

                unset($fields[$fieldName]);
            }
        }

        $validFields = $this->filteringFields($validFields, $excludedFields);
        if (empty($fields)) {
            return array_filter(array_replace(array_fill_keys($fieldKeys, null), $validFields));
        }

        $unmappedFields = $this->filteringRemainingFields($validFields, $fields, $excludedFields, $entityOrClass);
        foreach ($fields as $fieldName => $field) {
            if (\in_array($fieldName, $excludedFields, true)) {
                continue;
            }
            if (isset($unmappedFields[$fieldName])) {
                continue;
            }
            if (null === $field) {
                continue;
            }
            if (false === ($field['display'] ?? true)) {
                unset($validFields[$fieldName]);
                continue;
            }

            // Override with priority
            $validFields[$fieldName] = $field + $validFields[$fieldName];
        }

        // A column named otherwise than its property (Doctrine's ClassMetadata::$fieldNames, column => property).
        $aliasNames = [];
        foreach ($shape->entity->fields as $property => $field) {
            if ($field->column != $property) {
                $aliasNames[$field->column] = $property;
            }
        }

        $fields = $validFields + $unmappedFields;
        $aliasFields = array_filter($fields, fn ($k) => \in_array($k, $aliasNames), \ARRAY_FILTER_USE_KEY);

        $fields = array_transforms(fn ($k, $v): ?array => \array_key_exists($k, $aliasNames) ? [$aliasNames[$k], $aliasFields[$aliasNames[$k]]] : [$k, $v], $fields);

        return array_filter(array_replace(array_fill_keys($fieldKeys, null), $fields));
    }

    /**
     * Whether a property may be null: a field's mapping says so; an association, never; a name the class does not map
     * is its translation's field where it has translations (ClassMetadataManipulator::getMapping()'s `nullable`).
     */
    public function nullable(null|string|object $entityOrClass, string $property): bool
    {
        if (null === $entityOrClass || null === $this->manipulator->getShape($entityOrClass)) {
            return false;
        }
        if ($this->manipulator->hasAssociation($entityOrClass, $property)) {
            return false;
        }
        if ($this->manipulator->hasField($entityOrClass, $property)) {
            $shape = $this->manipulator->getShape($entityOrClass);
            $name = $this->manipulator->getFieldName($entityOrClass, $property) ?? $property;

            return $shape->entity->fields[$name]->nullable ?? false;
        }
        if ($this->manipulator->hasAssociation($entityOrClass, 'translations')) {
            return $this->nullable($this->manipulator->getAssociationTargetClass($entityOrClass, 'translations'), $property);
        }

        return false;
    }

    /**
     * A to-many association's order (property => direction), null for anything else: ClassMetadataManipulator::getMapping()'s
     * `orderBy`.
     *
     * @return array<string, mixed>|null
     */
    public function orderBy(null|string|object $entityOrClass, string $property): ?array
    {
        if (null === $entityOrClass || !$this->manipulator->hasAssociation($entityOrClass, $property) || !$this->manipulator->isToManySide($entityOrClass, $property)) {
            return null;
        }
        $name = $this->manipulator->getFieldName($entityOrClass, $property) ?? $property;

        return $this->manipulator->getShape($entityOrClass)?->entity->relations[$name]->orderBy;
    }

    /**
     * A persisted collection's owner and sides, null for a collection the application made or one whose owner is not
     * known: the collection's own (Doctrine's PersistentCollection) or the form's that holds it - the collection that
     * form's data, its parent's data the owner, its property path the property.
     */
    public function collection(mixed $collection, ?FormInterface $holder = null): ?CollectionMapping
    {
        if (!self::isPersisted($collection)) {
            return null;
        }

        // Doctrine's collection knows its owner and its association (instanceof loads nothing without doctrine/orm).
        if ($collection instanceof \Doctrine\ORM\PersistentCollection) {
            $owner = $collection->getOwner();
            $mapping = $collection->getMapping();
            $property = \is_array($mapping) ? $mapping['fieldName'] : $mapping->fieldName;
        } else {
            $owner = $holder?->getParent()?->getData();
            $property = null === $holder ? null : (string) ($holder->getPropertyPath() ?? $holder->getName());
        }
        if (!\is_object($owner) || null === $property || !$this->manipulator->hasAssociation($owner, $property)) {
            return null;
        }

        $name = $this->manipulator->getFieldName($owner, $property) ?? $property;
        $shape = $this->manipulator->getShape($owner);
        if (null === $shape || !$shape->isToMany($name)) {
            return null;
        }

        return new CollectionMapping($owner, $name, (string) $shape->targetClass($name), $shape->isOwningSide($name), $shape->mappedBy($name), $shape->inversedBy($name));
    }

    /**
     * Whether the entity closest to a form (or the entity given) owns the collection given or the one closest to the
     * form: the owner of a persisted collection is that entity; another collection is owned by the entity holding it
     * (ClassMetadataManipulator::isCollectionOwner()'s answer). Null where no entity or no collection is found.
     */
    public function isCollectionOwner(object $entityOrForm, ?Collection $collection = null): ?bool
    {
        $entity = $this->manipulator->isEntity($entityOrForm) ? $entityOrForm : $this->closestEntity($entityOrForm);
        if (null == $entity) {
            return null;
        }

        $collection ??= $this->manipulator->isEntity($entityOrForm) ? null : $this->closestEntityCollection($entityOrForm);
        if (!$collection instanceof Collection) {
            return null;
        }

        // Doctrine's collection names its owner (ClassMetadataManipulator also compared a translation's translatable, under
        // an interface name no class implements: that check never answered).
        if ($collection instanceof \Doctrine\ORM\PersistentCollection) {
            if (null === $collection->getOwner()) {
                return null;
            }

            return $entity === $collection->getOwner();
        }

        return in_class($entity, $collection) || $collection instanceof ArrayCollection;
    }

    /** The data of the form, or of the closest of its parents, that is an entity. */
    public function closestEntity(FormInterface|FormEvent $form): ?object
    {
        if ($form instanceof FormEvent) {
            $form = $form->getForm();
        }

        while (!$this->manipulator->isEntity($form->getData())) {
            $form = $form->getParent();
            if (null === $form) {
                return null;
            }
        }

        return $form->getData();
    }

    /** The data of the closest parent form whose own parent holds an entity: the collection a form is an entry of. */
    public function closestEntityCollection(FormInterface|FormEvent $form): mixed
    {
        if ($form instanceof FormEvent) {
            $form = $form->getForm();
        }

        do {
            $form = $form->getParent();
            if (null === $form) {
                return null;
            }
        } while (!$this->manipulator->isEntity($form->getParent()?->getData()));

        return $form->getData();
    }

    private function filteringFields(array $fields, array $excludedFields): array
    {
        $excludedFields = array_merge($this->globalExcludedFields, $excludedFields);

        $validFields = [];
        foreach ($fields as $fieldName => $field) {
            if (\in_array($fieldName, $excludedFields, true)) {
                continue;
            }

            $validFields[$fieldName] = $field;
        }

        return $validFields;
    }

    private function filteringRemainingFields(array $validFields, array $fields, array $excludedFields, null|string|object $entityOrClass): array
    {
        $unmappedFields = [];
        $validFieldKeys = array_keys($validFields);
        $unknowsFields = [];

        foreach ($fields as $fieldName => $field) {
            if (\in_array($fieldName, $excludedFields)) {
                continue;
            }
            if (\in_array($fieldName, $validFieldKeys, true)) {
                continue;
            }
            if (false === ($field['mapped'] ?? true)) {
                $unmappedFields[$fieldName] = $field;
                continue;
            }

            $unknowsFields[] = $fieldName;
        }

        if (\count($unknowsFields) > 0) {
            throw new \RuntimeException(sprintf("Field(s) '%s' doesn't exist in %s", implode(', ', $unknowsFields), \is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass));
        }

        return $unmappedFields;
    }

    /** The inverse sides of a class's associations, as form fields. */
    private function associationFormTypes(\Base\Database\Mapping\EntityShape $shape): array
    {
        $fields = [];
        foreach ($shape->associationNames() as $assocName) {
            if (!$shape->isInverseSide($assocName)) {
                continue;
            }

            $target = $shape->targetClass($assocName);
            if ($shape->isToOne($assocName)) {
                $nullable = $shape->entity->inheritance?->column->nullable ?? false;
                $fields[$assocName] = [
                    'type' => AssociationType::class,
                    'data_class' => $target,
                    'required' => !$nullable,
                    'allow_recursive' => false,
                ];

                continue;
            }

            $fields[$assocName] = [
                'type' => CollectionType::class,
                'entry_type' => AssociationType::class,
                'entry_options' => [
                    'data_class' => $target,
                ],
                'allow_add' => true,
                'by_reference' => false,
                'allow_recursive' => false,
            ];
        }

        return $fields;
    }
}
