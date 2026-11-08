<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Database\Attribute\Uploader;
use Base\Field\Behaviour\Upload;
use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Omnibridge\Symfony\Omnibase\Form\FormFactory;
use Omnibridge\Symfony\Omnibase\Form\FormFactoryInterface;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Base\Service\FileService;
use Base\Service\FileServiceInterface;
use Base\Service\MediaService;
use Base\Service\MediaServiceInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\View\AssetPaths;
use Base\Validator\Constraints\File;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Traversable;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class FileType extends AbstractType implements DataMapperInterface
{
    /** The variant of the neutral behaviour this type draws (Base\Field\Behaviour\Upload). */
    public const VARIANT = Upload::FILE;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /** The page's asset paths (omnibase's Twig environment answers them under Twig), for a thumbnail's link. */
    protected ?AssetPaths $assets;

    /**
     * @var CsrfTokenManagerInterface
     */
    protected CsrfTokenManagerInterface $csrfTokenManager;

    /**
     * @var FormFactoryInterface
     */
    protected FormFactoryInterface $formFactory;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;

    /**
     * @var FileServiceInterface
     */
    protected FileServiceInterface $fileService;

    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;

    /** * @var string */
    protected string $cacheDir;

    public function __construct(
        ParameterBagInterface     $parameterBag,
        TranslatorInterface       $translator,
        ?AssetPaths               $assets,
        ClassMetadataManipulator  $classMetadataManipulator,
        CsrfTokenManagerInterface $csrfTokenManager,
        FormFactory               $formFactory,
        AdvancedRouterInterface           $router,
        MediaService              $mediaService,
        ObfuscatorInterface       $obfuscator,
        string                    $cacheDir
    )
    {
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->translator = $translator;
        $this->assets = $assets;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory = $formFactory;
        $this->obfuscator = $obfuscator;

        $this->mediaService = $mediaService;
        $this->fileService = cast($mediaService, FileService::class);
        $this->cacheDir = $cacheDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'fileupload';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // FileType's defaults are the neutral behaviour's (Base\Field\Behaviour\Upload), with the script's address of this host.
        $resolver->setDefaults(Upload::DEFAULTS + ['sortable-js' => $this->parameterBag->get("base.vendor.sortablejs.javascript")]);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            return $value === null ? $options["data_class"] : $value;
        });

        $resolver->setNormalizer('clipboard', function (Options $options, $value) {
            if ($value) {
                return !$options["multiple"] || $options["dropzone"] !== null;
            }
            return false;
        });

        $resolver->setAllowedTypes("dropzone", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $form = $event->getForm();
            $data = $event->getData();

            $options["multiple"] = $this->formFactory->guessMultiple($form, $options);
            $options["sortable"] = $this->formFactory->guessSortable($form, $options);
            $form->add('file', HiddenType::class);

            $mimeTypes = $options["mime_types"] ?? Uploader::getMimeTypes($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
            $maxFilesize = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
            if (array_key_exists('max_size', $options) && $options["max_size"]) {
                $maxFilesize = min($maxFilesize, $options["max_size"]);
            }

            if (!$options["dropzone"]) {
                if ($options["title"] !== null) {
                    $form->add("title", TextType::class, $options["title"]);
                }
                if ($options["allow_url"]) {
                    $form->add("url", UrlType::class, [
                        "required" => $options["required"] && ($data === null) && ($options["cropper"] ?? null) === null,
                        "default_protocol" => null,
                    ]);
                }

                $form->add('raw', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                    "required" => $options["required"] && (!$options["allow_url"] && $data === null) && ($options["cropper"] ?? null) === null,
                    "multiple" => $options["multiple"],
                    "constraints" => [new File($maxFilesize, $mimeTypes)],
                    "disabled" => !$options["allow_reupload"] && $data !== null
                ]);
            }

            if ($options["allow_delete"]) {
                $form->add('delete', CheckboxType::class, ['required' => false]);
            }
        });

        // Process the uploaded file on submission
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            if (is_string($data)) {
                $cacheDir = $this->cacheDir . "/dropzone";
                $data = explode("|", $data);

                foreach ($data as $key => $uuid) {
                    if (!empty($uuid)) {
                        $data[$key] = $cacheDir . "/" . $uuid;
                    }
                }

                $data = empty($data) ? [] : array_map(function ($fname) {
                    if (file_exists($fname)) {
                        return new UploadedFile($fname, $fname);
                    } else {
                        return $fname !== null ? basename($fname) : null;
                    }
                }, $data);

                if (!$options["multiple"]) {
                    $data = $data[0] ?? null;
                }
            }

            if (empty($data)) {
                $data = null;
            }
            $event->setData($data);
        });

        if ($options["alt"] !== null) {
            $builder->add("alt", TextType::class, $options["alt"]);
        }
    }

    /** The behaviour this type draws (Base\Field\Behaviour\Upload): FileType's own, each child's its variant. */
    protected function upload(): Upload
    {
        return new Upload(static::VARIANT);
    }

    /**
     * What the block reads, from the neutral behaviour (plan "omnibase agnostic", step F5d) - the record's
     * stored files, their addresses, the sizes and types accepted, the dropzone's settings and address, an
     * image's thumbnail, modal and cropper -, the same omnibridge/laravel's renderer draws; whether it
     * holds several and in order, as this host's metadata guesses them.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $entity = $form->getParent()->getData();
        $options["multiple"] = $this->formFactory->guessMultiple($form, $options);
        $options["sortable"] = $this->formFactory->guessSortable($form, $options);

        $vars = $this->upload()->vars($options + [
            'upload_record' => $entity,
            'upload_property' => $form->getName(),
            'upload_data' => $form->getData(),
            'upload_entity' => $this->classMetadataManipulator->isEntity($entity),
            'upload_media' => $this->mediaService,
            'upload_files' => $this->fileService,
        ], $view->vars['value'], $view->vars['id']);
        $view->vars['attr'] = array_replace($view->vars['attr'], $vars['attr'] ?? []);
        unset($vars['attr'], $vars['file_value']);
        if (array_key_exists('thumbnail', $vars)) {
            // The thumbnail's path as the page links it (Base\View\AssetPaths: omnibase's Twig environment answers it under Twig).
            $vars['thumbnail'] = null !== $this->assets ? $this->assets->getAsset($options["thumbnail"]) : $options["thumbnail"];
        }
        $view->vars = array_replace($view->vars, $vars);
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

        $fileForm = current(iterator_to_array($forms));
        $isMultiple = $fileForm->getConfig()->getOption("multiple");
        if ($isMultiple) {
            if (!is_array($viewData) && !$viewData instanceof Collection) {
                $viewData = [$viewData];
            }
        } else {
            if (is_array($viewData) || $viewData instanceof Collection) {
                $viewData = first($viewData);
            }
        }

        if (is_array($viewData)) {
            $viewData = array_map("basename", $viewData);
        } elseif ($viewData instanceof Collection) {
            $viewData = $viewData->map(fn($f) => $f !== null ? basename($f) : null);
        } else {
            $viewData = $viewData !== null ? basename($viewData) : $viewData;
        }

        $fileForm->setData($viewData);
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $childForms = iterator_to_array($forms);
        $options = current($childForms)->getParent()->getConfig()->getOptions();

        $fileData = $childForms['file']->getData() ?? null;
        $rawData = null;
        if ($options["allow_reupload"]) {
            $rawData = $childForms['raw']->getData() ?? null;
        }

        $urlData = null;
        if ($options["allow_url"]) {
            $urlData = $childForms['url']->getData() ?? null;
            $urlData = filter_var($urlData, FILTER_VALIDATE_URL) ? fetch_url($urlData) : null;
        }

        $viewData = ($rawData ?: null) ?? ($urlData ?: null) ?? ($fileData ?: null) ?? null;
    }
}
