<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Contract\SymfonyConstraints;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * The Symfony form of a neutral FormDefinition (plan "omnibase agnostic", step F5b): each field
 * added under its kind's form type (Base\Field\Kinds, or the type named directly), its options
 * mapped as the admin maps a CRUD field's, its constraints translated (SymfonyConstraints), its
 * actions as buttons. Rendered by the site's theme and processed by Symfony as any form; a
 * definition's host options (setHostOptions(), Symfony's) win over what is derived.
 */
final class DefinitionForms
{
    public function __construct(private readonly FormFactoryInterface $factory)
    {
    }

    /** @param array<string, mixed> $options Symfony's form options, over the definition's */
    public function create(FormDefinition $definition, mixed $data = null, array $options = []): FormInterface
    {
        return $this->createBuilder($definition, $data, $options)->getForm();
    }

    /** @param array<string, mixed> $options Symfony's form options, over the definition's */
    public function createBuilder(FormDefinition $definition, mixed $data = null, array $options = []): FormBuilderInterface
    {
        // Named, as a CRUD form: base's decorated factory guards createBuilder() against entity data.
        $builder = $this->factory->createNamedBuilder($definition->getName(), FormType::class, $data, $options + $definition->getHostOptions() + self::formOptions($definition));

        foreach ($definition->getFields() as $name => $field) {
            $builder->add($name, $field->getFormType(), self::fieldOptions($field));
        }
        foreach ($definition->getActions() as $name => $action) {
            $builder->add($name, \Base\Field\Kinds::type($action['kind']), array_filter(['label' => $action['label'], 'attr' => $action['attr']], static fn (mixed $value): bool => null !== $value && [] !== $value));
        }

        return $builder;
    }

    /** @return array<string, mixed> */
    public static function formOptions(FormDefinition $definition): array
    {
        $options = [
            'method' => $definition->getMethod(),
            'data_class' => $definition->getDataClass(),
            'csrf_protection' => $definition->hasCsrf(),
        ];
        if ($definition->hasCsrf()) {
            $options['csrf_token_id'] = $definition->getIntent();
        }
        if (null !== $definition->getTarget()) {
            $options['action'] = $definition->getTarget();
        }
        if (null !== $definition->getTranslationDomain()) {
            $options['translation_domain'] = $definition->getTranslationDomain();
        }
        if ([] !== $definition->getAttr()) {
            $options['attr'] = $definition->getAttr();
        }

        return $options;
    }

    /**
     * A field's generic settings as Symfony form options; its explicit options always win.
     *
     * @return array<string, mixed>
     */
    public static function fieldOptions(FieldDescriptor $field): array
    {
        $options = [];
        foreach ([
            'label' => $field->getLabel(),
            'required' => $field->isRequired(),
            'disabled' => $field->isDisabled(),
            'empty_data' => $field->getEmptyData(),
            'help' => $field->getHelp(),
        ] as $name => $value) {
            if (null !== $value) {
                $options[$name] = $value;
            }
        }
        if ([] !== $field->getTranslationParameters()) {
            $options['label_translation_parameters'] = $field->getTranslationParameters();
        }
        if (true === $field->isVirtual() || \in_array($field->getKind(), Kind::UNMAPPED_KINDS, true)) {
            $options['mapped'] = false;
        }
        if ([] !== $field->getHtmlAttributes()) {
            $options['attr'] = $field->getHtmlAttributes();
        }
        if ([] !== $constraints = $field->getConstraints()) {
            $options['constraints'] = SymfonyConstraints::translate($constraints);
        }

        return array_replace_recursive($options, $field->getOptions());
    }
}
