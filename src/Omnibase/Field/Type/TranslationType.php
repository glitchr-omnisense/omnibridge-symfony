<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Omnibridge\Symfony\Omnibase\Form\FormMapping;
use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Database\Entity\Extension\TranslationInterface;

use Base\Field\Behaviour\Translation;
use Base\Service\LocalizerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Traversable;
use UnexpectedValueException;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Omnibridge\Symfony\Omnibase\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class TranslationType extends AbstractType implements DataMapperInterface
{
    /**
     * @var LocalizerInterface
     */
    protected $localizer = null;

    protected $fallbackLocales = [];

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, LocalizerInterface $localizer)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->localizer = $localizer;
    }

    public function getBlockPrefix(): string
    {
        return 'translatable';
    }

    /**
     * @return string|null
     */
    public function getDefaultLocale()
    {
        return $this->localizer->getDefaultLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,

            'locale' => $this->localizer->getLocale(),
            'locale_options' => [],

            'single_locale' => false,
            'default_locale' => $this->localizer->getDefaultLocale(),
            'required_locales' => [],
            'available_locales' => $this->localizer->getAvailableLocales(),

            'by_reference' => false,
            'empty_data' => fn(FormInterface $form) => new ArrayCollection(),

            'autoload' => true,
            'fields' => [],
            'excluded_fields' => [],

            'translation_class' => null,
            'translatable_class' => null,
            'multiple' => false
        ]);

        $resolver->setNormalizer('required_locales', fn (Options $options, $requiredLocales) => Translation::requiredLocales($requiredLocales, $options['available_locales']));
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            // The languages drawn, which part is required, a callable `required` called: omnibase's neutral Translation,
            // which the hosts without Symfony's forms ask too.
            ['locales' => $locales, 'default' => $defaultLocale] = Translation::locales($options, $data instanceof Collection ? array_map('strval', $data->getKeys()) : [$options["locale"]]);

            $translationClass = $this->getTranslationClass($form);
            $translationFields = $this->getTranslationFields($translationClass, $options);

            foreach ($locales as $locale => $fieldsLocale) {
                if (!isset($translationFields[$fieldsLocale])) {
                    continue;
                }

                $entityOptions = [
                    'class' => $translationClass,
                    'autoload' => $options["autoload"],
                    'fields' => $translationFields[$fieldsLocale],
                    'excluded_fields' => $options['excluded_fields'],
                    'keep_indexes' => false
                ];

                $options["locale"] = $locale;

                if (null !== $entryRequired = Translation::entryRequired($locale, $defaultLocale, $options)) {
                    $entityOptions["entry_required"] = $entryRequired;
                }

                foreach ($entityOptions["fields"] as &$translationField) {
                    $translationField = Translation::required($translationField, $entityOptions["entry_required"] ?? false, $locale, $options);
                }
                unset($translationField);

                $entityOptions["allow_null"] = true;

                if ($options["multiple"]) {
                    $entityOptions["multiple"] = true;
                    $entityOptions["group"] = false;
                    $entityOptions["row_group"] = false;
                    $entityOptions["allow_add"] = false;
                    $entityOptions["allow_delete"] = false;
                    $entityOptions["recursive"] = true;
                }

                $form->add($locale, AssociationType::class, $entityOptions);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $multiple = $event->getForm()->getConfig()->getOption("multiple");
            if (!$multiple) {
                $data = [$data];
            }

            foreach ($data as $key => $array) {
                foreach ($array as $locale => $translation) {
                    if (FormMapping::isPersisted($array)) {
                        if (!$translation instanceof TranslationInterface) {
                            $data[$key]->removeElement($translation);
                        } elseif ($translation->isEmpty()) {
                            $data[$key]->removeElement($translation);
                        } else {
                            $translation->setLocale($locale);
                        }
                    } else {
                        if (!$translation instanceof TranslationInterface) {
                            unset($data[$key][$locale]);
                        } elseif (empty($translation)) {
                            unset($data[$key][$locale]);
                        }
                    }
                }
            }

            $event->setData(!$multiple ? $data[0] : $data);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["locale"] = $options["locale"];
        $view->vars["single_locale"] = $options["single_locale"];

        $view->vars["default_locale"] = $options["default_locale"];
        $view->vars["available_locales"] = $options["available_locales"];
        $view->vars["required_locales"] = $options["required_locales"];
        $view->vars["nonempty_locales"] = [];

        foreach (iterator_to_array($form) as $locale => $child) {
            if ($child->getViewData() instanceof TranslationInterface && !$child->getViewData()->isEmpty()) {
                $view->vars["nonempty_locales"][] = $locale;
            } elseif ($child->getViewData() instanceof Collection and array_filter($child->getViewData()->toArray()) !== []) {
                $view->vars["nonempty_locales"][] = $locale;
            }
        }

        $view->vars["compatible_locale"] = $this->localizer->getDefaultLocale();
        foreach ($this->localizer->getAvailableLocales() as $availableLocale) {
            if ($availableLocale == $this->localizer->getDefaultLocale()) {
                continue;
            }
            if ($this->localizer->compatibleLocale($this->localizer->getLocale(), $availableLocale, $view->vars["nonempty_locales"])) {
                $view->vars["compatible_locale"] = $availableLocale;
            }
        }

        $view->vars["translations_empty"] = true;
        foreach ($form->all() as $childForm) {
            if (count($childForm->all())) {
                $view->vars["translations_empty"] = false;
                break;
            }
        }
    }

    public function getTranslationFields(string $translationClass, array $options): array
    {
        // Check excluded fields exists.. (for dev sanity)
        $shape = $this->classMetadataManipulator->getShape($translationClass) ?? throw new \InvalidArgumentException("Entity expected, '" . $translationClass . "' is not an entity.");
        $excludedFields = $options["excluded_fields"] ?? [];
        foreach ($excludedFields as $field) {
            if (!property_exists($shape->className(), $field)) {
                throw new Exception("Field \"" . $field . "\" requested for exclusion doesn't exist in \"" . $translationClass . "\"");
            }
        }

        // Prepare raw fields
        $fields = FormMapping::of($this->classMetadataManipulator)->fields($translationClass, $options["fields"], $options["excluded_fields"]);
        if (!$options["autoload"]) {
            $fields = array_filter($fields, fn($k) => array_key_exists($k, $options["fields"]), ARRAY_FILTER_USE_KEY);
        }

        // Each language's fields (omnibase's neutral Translation::perLocale())
        $translationFields = Translation::perLocale($fields, $options['available_locales']);

        return $translationFields;
    }

    private function getTranslationClass(FormInterface $form): string
    {
        // Looking at translation class
        $translationClass = $form->getConfig()->getOption("translation_class");
        if (is_subclass_of($translationClass, TranslationInterface::class)) {
            return $translationClass;
        }

        // Try to determine the translatable interface based on data_class from form
        $translatableClass = $this->getTranslatableClass($form);

        // Determine from parent too
        $formInit = $form;
        while ($translatableClass === null) {
            if ($form->getParent() === null) {
                break;
            }

            $translatableClass = $this->getTranslatableClass($form->getParent());
            $form = $form->getParent();
        }

        if (!$translatableClass) {
            throw new Exception("No \"translation_class\" found in \"" . $form->getName() . "\" and no hint (data_class or data) from inherited from FormType \"" . $formInit->getName() . "\" (" . get_class($formInit->getConfig()->getType()->getInnerType()) . ") or any of its parents");
        }

        if (!is_subclass_of($translatableClass, TranslatableInterface::class)) {
            throw new Exception("Translatable interface not implemented in \"" . $translatableClass . "\"");
        }

        return $translatableClass::getTranslationEntityClass(); //, false);
    }

    /**
     * @param FormInterface $form
     * @return TranslatableInterface|mixed|string|null
     */
    private function getTranslatableClass(FormInterface $form)
    {
        // Looking at translatable interface using data_class
        $translatableClass = $form->getConfig()->getOption("translatable_class");
        if (is_subclass_of($translatableClass, TranslatableInterface::class)) {
            return $translatableClass;
        }

        // Looking at translatable interface using data_class
        $translatableClass = $form->getConfig()->getDataClass();
        if (is_subclass_of($translatableClass, TranslatableInterface::class)) {
            return $translatableClass;
        }

        // Looking at translatable interface using data
        $translatableClass = $form->getConfig()->getData();
        if (is_subclass_of($translatableClass, TranslatatableInterface::class)) {
            return get_class($translatableClass);
        }

        return null;
    }

    /**
     * @param $viewData
     * @param Traversable $forms
     * @return void
     */
    public function mapDataToForms($viewData, Traversable $forms): void
    {
        $multiple = current(iterator_to_array($forms))->getParent()->getConfig()->getOption("multiple");
        foreach (iterator_to_array($forms) as $locale => $form) {
            if (!$multiple) {
                $form->setData($viewData[$locale] ?? null);
            } else {
                $newViewData = new ArrayCollection();
                foreach ($viewData ?? [] as $key => $data) {
                    $newViewData[$key] = $data[$locale] ?? null;
                }

                $form->setData($newViewData);
            }
        }
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $multiple = current(iterator_to_array($forms))->getParent()->getConfig()->getOption("multiple");

        foreach (iterator_to_array($forms) as $locale => $form) {
            $data = $form->getData();

            if (!$multiple) {
                if ($data instanceof TranslationInterface) {
                    if ($data->isEmpty()) {
                        unset($viewData[$locale]);
                    } else {
                        $viewData[$locale] = is_array($data) && empty($data) ? null : $data;
                        $viewData[$locale]->setLocale($locale);
                    }
                }
            } else {
                foreach ($data as $key => $translation) {
                    if ($translation === null) {
                        continue;
                    }
                    if (!($translation instanceof TranslationInterface)) {
                        throw new UnexpectedValueException("Object expected to be an instance of TranslationInterface, \"" . (is_object($translation) ? get_class($translation) : $translation) . "\" received.");
                    }

                    $translation->setLocale($locale);

                    // The owner of a collection the persistence read, where it is known without the form (Doctrine's
                    // collection names it; FormMapping).
                    if (null !== $collection = FormMapping::of($this->classMetadataManipulator)->collection($viewData[$key])) {
                        $translatable = $collection->owner;
                        if ($translatable instanceof TranslatableInterface) {
                            $translation->setTranslatable($translatable);
                        }
                    }

                    $viewData[$key][$locale] = $translation;
                }
            }
        }
    }
}
