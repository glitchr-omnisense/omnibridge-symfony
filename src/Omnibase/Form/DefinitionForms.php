<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Contract\SymfonyConstraints;
use Symfony\Component\Form\ClearableErrorsInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * The Symfony form of a neutral FormDefinition (plan "omnibase agnostic", step F5b): each field
 * added under its kind's form type (Base\Field\Kinds, or the type named directly), its options
 * mapped as the admin maps a CRUD field's, its constraints translated (SymfonyConstraints), its
 * actions as buttons. Rendered by the site's theme and processed by Symfony as any form; a
 * definition's host options (setHostOptions(), Symfony's) win over what is derived.
 */
final class DefinitionForms
{
    /** Custom option of a Kind::REPEATED field: the Symfony form type of its two inputs, in place of their kind's. */
    public const OPTION_REPEATED_TYPE = 'symfony.repeated_type';

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
        self::populate($builder, $definition);

        return $builder;
    }

    /** A definition's fields, actions and rewrites added to a form being built (DefinitionType's, a named builder's). */
    public static function populate(FormBuilderInterface $builder, FormDefinition $definition): void
    {
        foreach ($definition->getFields() as $name => $field) {
            $builder->add($name, $field->getFormType(), self::fieldOptions($field));
        }
        foreach ($definition->getActions() as $name => $action) {
            $builder->add($name, \Base\Field\Kinds::type($action['kind']), array_filter(['label' => $action['label'], 'attr' => $action['attr']], static fn (mixed $value): bool => null !== $value && [] !== $value) + $action['options']);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($definition): void {
            if (\is_array($data = $event->getData())) {
                $event->setData($definition->rewrite($data));
            }
        });

        if ([] !== $fields = $definition->getEntityErrorFields()) {
            $builder->addEventListener(FormEvents::POST_SUBMIT, static fn (FormEvent $event) => self::moveEntityErrors($event->getForm(), $fields), -128);
        }
    }

    /**
     * omnibase checks the record a form makes (validation_entity) and gives every violation to the
     * form itself: those about these fields go under them, the others stay.
     *
     * @param list<string> $fields
     */
    private static function moveEntityErrors(FormInterface $form, array $fields): void
    {
        if (!$form instanceof ClearableErrorsInterface) {
            return;
        }
        $errors = iterator_to_array($form->getErrors());
        // A class-level uniqueness names no path: its violation is known by its constraint.
        $about = static function (FormError $error) use ($fields, $form): ?string {
            $cause = $error->getCause();
            if (!$cause instanceof ConstraintViolationInterface) {
                return null;
            }
            foreach ((array) ($cause->getConstraint()->fields ?? []) as $field) {
                if (\in_array($field, $fields, true) && $form->has($field)) {
                    return $field;
                }
            }

            return null;
        };
        if ([] === array_filter($errors, static fn (FormError $error): bool => null !== $about($error))) {
            return;
        }
        $form->clearErrors();
        foreach ($errors as $error) {
            null !== ($field = $about($error)) ? $form->get($field)->addError($error) : $form->addError($error);
        }
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
        if (false !== $definition->getGuard()) {
            $options['guard'] = $definition->getGuard();
        }
        if (null !== $definition->getValidationGroups()) {
            $options['validation_groups'] = $definition->getValidationGroups();
        }
        if (null !== $definition->getValidationEntity()) {
            $options['validation_entity'] = $definition->getValidationEntity();
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

        $options = array_replace_recursive($options, $field->getOptions());
        if (null !== $choices = \Base\Form\Definition\FormBinder::choices($field)) {
            $options['choices'] = $choices; // what a callable gives: Symfony's types take the list
        }
        if (Kind::REPEATED === $field->getKind()) {
            // The two inputs' kind as its type here, a site's own host type when it names one.
            $options['type'] = $field->getCustomOption(self::OPTION_REPEATED_TYPE) ?? \Base\Field\Kinds::type((string) ($options['type'] ?? Kind::TEXT));
        }

        return $options;
    }
}
