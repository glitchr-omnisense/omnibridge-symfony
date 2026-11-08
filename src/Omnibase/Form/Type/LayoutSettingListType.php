<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Base\Database\Attribute\Uploader;
use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Omnibridge\Symfony\Omnibase\Field\Type\AvatarType;
use Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType;
use Omnibridge\Symfony\Omnibase\Field\Type\FileType;
use Omnibridge\Symfony\Omnibase\Field\Type\ImageType;
use Omnibridge\Symfony\Omnibase\Field\Type\TranslationType;
use Omnibridge\Symfony\Omnibase\Form\Common\AbstractType;
use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\SettingBag;
use Base\Service\SettingBagInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\StringUtil;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class LayoutSettingListType extends AbstractType implements DataMapperInterface
{
    protected SettingBagInterface $settingBag;

    protected LocalizerInterface $localizer;

    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(SettingBag $settingBag, Localizer $localizer, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->settingBag = $settingBag;
        $this->localizer = $localizer;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string
    {
        return '_base_' . StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'fields' => [],
            'fields[single_locale]' => [],
            'excluded_fields' => [],
            'locale' => null,
            'attr' => [
                'class' => 'needs-validation',
            ],
        ]);
    }

    /**
     * @param $data
     * @param $from
     * @param $to
     * @return array
     * @throws \Exception
     */
    public function getFormattedData($data, $from = '.', $to = '-')
    {
        $newData = [];
        if (!$data) {
            return [];
        } elseif (is_associative($data)) {
            foreach ($data as $name => $value) {
                $newData[str_replace($from, $to, $name)] = $value;
            }
        } elseif (is_subclass_of($data, SettingBag::class)) {
            foreach ($data->all() as $setting) {
                $newData[str_replace($from, $to, $setting->getPath())] = $setting->getValue();
            }
        } elseif (is_subclass_of($data, Setting::class)) {
            $newData[str_replace($from, $to, $data->getPath())] = $data->getValue();
        } else {
            throw new \Exception('Unexpected data provided (expecting either associative array, Setting or BaseSetting)');
        }

        return $newData;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        // Upload fields that were already holding a file when the page was
        // drawn, keyed by the SettingIntl carrying them. See the POST_SUBMIT
        // listener at the end of this method for what it is for.
        $storedUploads = [];

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options, &$storedUploads) {
            $settingBag = [];

            $formattedFields = $this->getFormattedData($options['fields']);
            foreach ($formattedFields as $formattedField => $fieldOptions) {
                $field = str_replace('-', '.', $formattedField);

                $settingBag[$formattedField] = $this->settingBag->getRawScalar($field, false) ?? new Setting($field);
            }

            $fields = ['value' => null];

            $unvData = [];
            $intlData = [];

            foreach ($settingBag as $formattedField => $setting) {
                // Exclude requested fields
                $field = str_replace('-', '.', $formattedField);
                if (in_array($field, $options['excluded_fields'])) {
                    continue;
                }

                // Set field options
                $fieldOptions = $options['fields'][$field];
                $fieldOptions['attr'] = $opts['attr'] ?? [];
                $fieldOptions['form_type'] = $fieldOptions['form_type'] ?? TextType::class;

                // Set default label
                if (!array_key_exists('label', $fieldOptions)) {
                    $label = explode('-', trim(str_lstrip($formattedField, ['app-settings', 'base-settings']), ' -'));

                    // Split camelCase/PascalCase too, not just the "-" and
                    // "_" separators below: a path segment written as one
                    // run of words ("exchangeRatesApi", "APIKeyLabel")
                    // otherwise reached the form as a single unreadable
                    // token. Two passes are needed - the first breaks a
                    // lower/digit -> upper boundary ("exchangeRates" =>
                    // "exchange Rates"), the second the acronym -> word one
                    // ("APIKey" => "API Key"), which the first cannot see
                    // because both characters around it are uppercase.
                    $label = array_map(
                        static fn (string $part): string => preg_replace('/\s+/', ' ', trim(preg_replace(
                            ['/(?<=[a-z0-9])([A-Z])/', '/(?<=[A-Z])([A-Z][a-z])/'],
                            ' $1',
                            $part
                        ))),
                        $label
                    );

                    $fieldOptions['label'] = $setting->getLabel() ?? mb_ucwords(str_replace('_', ' ', implode(' - ', $label)));
                }

                if (FileType::class == $fieldOptions['form_type'] || ImageType::class == $fieldOptions['form_type'] || AvatarType::class == $fieldOptions['form_type']) {
                    $fieldOptions['max_size'] = $fieldOptions['max_size'] ?? Uploader::getMaxFilesize(SettingIntl::class, 'value');
                    $fieldOptions['mime_types'] = $fieldOptions['mime_types'] ?? Uploader::getMimeTypes(SettingIntl::class, 'value');
                    // THIS setting's own stored value, not $settingValue -
                    // that variable is assigned in the per-locale loop
                    // further down, so reading it here picked up whatever
                    // the PREVIOUS setting in this same loop happened to
                    // leave behind. empty_data is what a file field binds
                    // when no new file is chosen, so on a plain Save every
                    // untouched upload field adopted another setting's
                    // value: reported live, the site logo came back holding
                    // "image.webp" while an unrelated redirect setting came
                    // back holding the logo's URL.
                    $ownValue = null;
                    foreach ($setting->getTranslations() as $ownTranslation) {
                        $ownValue = $ownTranslation->getValue();
                        if (null !== $ownValue) {
                            break;
                        }
                    }
                    $fieldOptions['empty_data'] = $ownValue ?? '';
                }

                if (!array_key_exists('help', $fieldOptions)) {
                    $fieldOptions['help'] = $setting->getHelp() ?? '';
                }
                if (!array_key_exists('disabled', $fieldOptions)) {
                    $fieldOptions['disabled'] = $setting->isLocked() ?? false;
                }

                //
                // Check if expected to be translatable
                $isTranslatable = $fieldOptions['translatable'] ?? false;
                $fieldOptions = array_key_removes($fieldOptions, 'translatable');

                $fields['value'][$formattedField] = $fieldOptions;

                $translations = $setting->getTranslations();
                foreach ($translations as $locale => $settingTranslation) {
                    $settingValue = $settingTranslation->getValue();

                    if (in_array($fieldOptions['form_type'], [FileType::class, ImageType::class, AvatarType::class], true)
                        && is_string($settingValue) && '' !== $settingValue) {
                        $storedUploads[spl_object_id($settingTranslation)] = [
                            'translation' => $settingTranslation,
                            'raw' => $settingTranslation->getValueRaw(),
                            'displayed' => basename($settingValue),
                        ];
                    }

                    switch ($fieldOptions['form_type']) {
                        case DateTimePickerType::class:
                            $datetime = $settingValue instanceof \DateTime ? $settingValue : null;
                            if (!$datetime && is_string($settingValue)) {
                                $datetime = new \DateTime($settingValue);
                            } else {
                                $datetime = null;
                            }

                            $settingTranslation->setValue($datetime);
                            break;

                        case CheckboxType::class:
                            $bool = !empty($settingValue) && '0' != $settingValue;
                            $settingTranslation->setValue($bool);
                            break;
                    }
                }

                if ($isTranslatable) {
                    $intlData[$formattedField] = $translations;
                } else {
                    $unvData[$formattedField] = $translations;
                }
            }

            $form = $event->getForm();
            if ($intlData) {
                $form->add('intl', TranslationType::class, [
                    'fields' => $fields,
                    'autoload' => false,
                    'multiple' => true,
                    'required_locales' => [$this->localizer->getDefaultLocale()],
                    'translation_class' => SettingIntl::class,
                ]);

                $form->get('intl')->setData($intlData);
            }

            if ($unvData) {
                $form->add('unv', TranslationType::class, [
                    'fields' => $fields,
                    'autoload' => false,
                    'multiple' => true,
                    'locale' => $this->localizer->getDefaultLocale(),
                    'single_locale' => true,
                    'translation_class' => SettingIntl::class,
                ]);

                $form->get('unv')->setData($unvData);
            }

            if (count($fields) > 0) {
                $form->add('valid', SubmitType::class, ['attr' => ['class' => 'btn btn-primary'], 'translation_domain' => 'controllers', 'label_format' => 'admin_settings.valid']);
            }
        });

        // Put back the upload references that a plain Save would otherwise
        // erase.
        //
        // A file-backed setting is STORED as its upload uuid but READ BACK as
        // the resolved public path - SettingIntl::getValue() runs it through
        // Uploader::getPublic() - and FileType renders that path as a hidden
        // input holding only its basename. Submitting the page therefore
        // hands the field's own display name back as if it were a new value,
        // and the child data mapper writes it straight onto the SettingIntl.
        // Saving /admin/settings without touching the logo replaced
        // "c241f214-dc84-4b36-9cd9-8e16f6972f51" with "image.webp", and the
        // site logo was gone.
        //
        // Nothing was uploaded in that case, so there is nothing to save:
        // whenever a field comes back holding exactly the name it was drawn
        // with, restore the stored reference. A real upload arrives as a
        // different name (or as a File) and is left alone; clearing a field
        // submits nothing and is left alone too, so deleting a logo still
        // works.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function () use (&$storedUploads) {
            foreach ($storedUploads as $stored) {
                $current = $stored['translation']->getValueRaw();
                if (!is_string($current) || $current !== $stored['displayed'] || $current === $stored['raw']) {
                    continue;
                }

                $stored['translation']->setValue($stored['raw']);
            }
        });
    }

    /**
     * @param $viewData
     * @param \Traversable $forms
     * @return void
     */
    public function mapDataToForms($viewData, \Traversable $forms): void
    {
    }

    /**
     * @param \Traversable $forms
     * @param $viewData
     * @return void
     * @throws \Exception
     */
    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        foreach (iterator_to_array($forms) as $formName => $form) {
            if ('valid' == $formName) {
                continue;
            } elseif ('intl' == $formName || 'unv' == $formName) {
                foreach ($form->getData() as $formattedField => $translations) {
                    $field = str_replace('-', '.', $formattedField);
                    foreach ($translations as $locale => $translation) {
                        $viewData[$field] = $viewData[$field] ?? $this->settingBag->getRawScalar($field) ?? new Setting($field);
                        if ($viewData[$field]->isLocked()) {
                            throw new \Exception('Setting "' . $viewData[$field]->getPath() . '" is locked, you cannot edit this variable.');
                        }

                        // A BOOLEAN is never "empty" here. `[] == false` is
                        // true in PHP, so the old `[] == $value` guard
                        // rewrote every switch saved as OFF to NULL - and a
                        // null setting is *unset*, so it falls back to its
                        // code-defined default, silently re-enabling what
                        // the operator had just turned off. Empty
                        // string/array still normalise to null, which is
                        // what the guard was for (clearing a text or
                        // multi-value field unsets it).
                        if (!\is_bool($translation->getValue()) && [] == $translation->getValue()) {
                            $translation->setValue(null);
                        }
                        $viewData[$field]->translate($locale)->setValue($translation->getValue() ?? null);
                    }
                }
            } else {
                $field = str_replace('-', '.', $formName);
                if (!$viewData[$formName] instanceof Setting) {
                    $viewData[$formName] = $viewData[$formName] ?? new Setting($formName);
                }

                if ($viewData[$formName]->isLocked()) {
                    throw new \Exception('Setting "' . $viewData[$formName]->getPath() . '" is currently locked.');
                }

                $translation = $form->getViewData();
                $locale = $translation->getLocale();

                if ([] == $translation->getValue()) {
                    $translation->setValue(null);
                }
                $viewData[$formName]->translate($locale)->setValue($translation->getValue() ?? null);
            }
        }
    }
}
