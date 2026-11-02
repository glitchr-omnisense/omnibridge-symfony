<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Database\Attribute\Uploader;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
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

class FileType extends AbstractType implements DataMapperInterface
{
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
        $resolver->setDefaults([
            'class' => null,
            'empty_data' => null,
            'webpack_entry' => "form.dropzone",

            'dropzone' => [],

            'allow_delete' => true,
            "allow_delete[confirmation]" => true,
            "allow_cancel[confirmation]" => true,

            'multiple' => null,
            'clipboard' => false,

            'href' => null,
            'title' => null,
            'allow_url' => false,
            'allow_reupload' => true,

            'sortable' => null,
            'sortable-js' => $this->parameterBag->get("base.vendor.sortablejs.javascript"),

            'lightbox' => ['resizeDuration' => 500, 'fadeDuration' => 250, 'imageFadeDuration' => 100],
            'alt' => null,

            'thumbnail_width' => null,
            'thumbnail_height' => 250,
            'max_size' => null,
            'max_files' => null,
            'mime_types' => [],
            "data_mapping" => null,
            "parallel_uploads" => 5,
            "upload_multiple" => false,

            "inline" => false
        ]);

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

    /**
     * Storage-aware existence check.
     *
     * `file_exists()` only ever answers for the LOCAL filesystem. Once media
     * moved to S3 it returned false for every stored file, so the three view
     * vars below (path / download / clippable) were built from an empty set and
     * the dropzone received bare basenames instead of URLs: it then requested
     * "image.webp" relative to the current admin page, got a 404, and Dropzone
     * re-emitted its own error Event as the thumbnail source - which is how
     * twelve <img src="[object Event]"> ended up on a gallery edit page.
     */
    private function fileIsAvailable(?string $path): bool
    {
        if (null === $path || '' === $path) {
            return false;
        }

        if (file_exists($path)) {
            return true;
        }

        // Remote (or otherwise non-local) storage. FileService::getMimeType()
        // reads through flysystem first, so a resolvable mime type means the
        // storage can see the object - which is exactly the question here.
        try {
            return null !== $this->fileService->getMimeType($path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $parent = $form->getParent();
        $entity = $parent->getData();

        $options["multiple"] = $this->formFactory->guessMultiple($form, $options);
        $options["sortable"] = $this->formFactory->guessSortable($form, $options);

        $view->vars["inline"] = $options["inline"];

        $view->vars["lightbox"] = null;
        if (is_array($options["lightbox"])) {
            $view->vars["lightbox"] = json_encode($options["lightbox"]);
        }

        if ($this->classMetadataManipulator->isEntity($entity)) {
            $files = Uploader::get($entity, $form->getName());
            if (!is_array($files)) {
                $files = [$files];
            }

            $files = array_map(fn($f) => $f ? $f->getPath() : null, $files);

            $propertyType = Uploader::getTypeOfField($entity, $form->getName());
            if ($options["multiple"] && $propertyType != "array") {
                $view->vars['max_files'] = 1;
            }
            
        } else {
            $files = $form->getData();
        }

        if (!is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $view->vars["files"] = array_filter($files);

        $view->vars["allow_reupload"] = $options["allow_reupload"];
        $view->vars["allow_url"] = $options["allow_url"];

        $view->vars['max_files'] = $view->vars['max_files'] ?? $options["max_files"];
        // Same min() the CONSTRAINT applies in buildForm() above, rather than
        // re-resolving from scratch and discarding an explicitly passed
        // max_size. Without it the displayed hint and the enforced limit
        // could disagree, and did: getMaxFilesize() starts from PHP's ini
        // ceiling and only narrows it when it finds an #[Uploader] attribute
        // on ($class ?? $entity). For a TRANSLATABLE file field, $entity is
        // the per-locale translation row - present for a locale that already
        // has a value, null for one that doesn't. So the same field
        // advertised "2MB" under the locale that had an upload and PHP's raw
        // "33MB" under the empty ones, while all of them actually rejected
        // anything over 2MB. Reported live on /admin/settings (Logo).
        $maxFilesize = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
        if (!empty($options["max_size"])) {
            $maxFilesize = min($maxFilesize, $options["max_size"]);
        }

        $view->vars['max_size'] = $options["max_size"] = $maxFilesize;

        $mimeTypes = $options["mime_types"];
        if (!$mimeTypes && $entity) {
            $mimeTypes = Uploader::getMimeTypes($options["class"] ?? $entity, $options["data_mapping"] ?? $form->getName());
        }

        $view->vars["mime_types"] = $mimeTypes;

        $view->vars['value'] = Uploader::getPublic($entity ?? null, $options["data_mapping"] ?? $form->getName()) ?? $files;

        $view->vars['clippable'] = $view->vars['path'] = $view->vars['download'] = json_encode([]);
        $view->vars['previews'] = json_encode([]);
        if (!is_array($view->vars["value"]) && $options["multiple"]) {
            $view->vars["value"] = [$view->vars["value"]];
        } elseif (is_array($view->vars["value"]) && !$options["multiple"]) {
            $view->vars["value"] = first($view->vars["value"]);
        }

        if (is_array($view->vars['value'])) {

            if ($view->vars['value']) {

                // Keyed by POSITION, not basename: every uploaded image is stored
                // as "<hash>/image.webp", so basenaming the key collapsed a
                // twelve-photo gallery into a single map entry and eleven
                // thumbnails had no URL at all. The client walks the value list
                // in order, so the index is the one key guaranteed unique.
                $view->vars['path'] = json_encode(array_values(array_transforms(function ($k, $v): ?array {
                    if (!$this->fileIsAvailable($v)) {
                        return null;
                    }

                    // thumbnail(), not image(): these URLs feed 124px dropzone
                    // previews, and handing them the ORIGINAL made a twelve-photo
                    // gallery pull twelve 2880x2160 files (~400KB each, ~5MB of
                    // originals to draw thumbnails) - enough of them stalled that
                    // only 7 of 12 ever painted. 1024px keeps the lightbox usable
                    // while costing a fraction of that.
                    return [$k, $this->fileService->isImage($v)
                        ? $this->mediaService->thumbnail($v, 1024, 1024)
                        : $this->mediaService->linkable($v)];
                }, array_filter($view->vars['value']))));

                // What the 124px dropzone previews actually DISPLAY. `path`
                // above stays at 1024px because it is what the lightbox, the
                // clipboard and "open in a tab" hand out, but drawing a dozen
                // 1024px images into 124px boxes still fetched megabytes and,
                // on a cold cache, timed out often enough that half a gallery
                // never painted. Square-cropped (OUTBOUND) so every preview is
                // the same rounded tile whatever the photo's aspect ratio.
                // Non-images keep the plain link, and the list stays aligned
                // with `path` position for position - the client walks both by
                // index.
                $view->vars['previews'] = json_encode(array_values(array_transforms(function ($k, $v): ?array {
                    if (!$this->fileIsAvailable($v)) {
                        return null;
                    }

                    return [$k, $this->fileService->isImage($v)
                        ? $this->mediaService->thumbnailOutbound($v, 320, 320)
                        : $this->mediaService->linkable($v)];
                }, array_filter($view->vars['value']))));

                $view->vars['download'] = json_encode(array_transforms(function ($k, $v): ?array {
                    return $this->fileIsAvailable($v) ? [basename($v), $this->fileService->downloadable($v)] : null;
                }, array_filter($view->vars['value'])));

                $view->vars['clippable'] = json_encode(array_transforms(function ($k, $v): ?array {
                    return $this->fileIsAvailable($v) ? [basename($v), $this->fileService->isImage($v)] : null;
                }, array_filter($view->vars['value'])));
            }

            $view->vars["value"] = implode("|", array_map(fn($v) => $v !== null ? basename($v) : null, $view->vars["value"]));

        } else {

            $view->vars['path'] = $view->vars["value"];
            $view->vars['previews'] = $view->vars["value"];
            $view->vars['download'] = $this->fileService->downloadable($view->vars["value"]);
            $view->vars['clippable'] = $this->fileService->isImage($view->vars["value"]);
        }

        $view->vars["clipboard"] = $options["clipboard"];
        $view->vars["sortable"] = true;
        $view->vars['dropzone'] = null;
        $view->vars["ajax"] = null;
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['allow_delete'] = $options['allow_delete'];
        $view->vars['href'] = $options["href"];
        $view->vars["confirm_action"] = $options["allow_cancel[confirmation]"] || $options["allow_delete[confirmation]"];

        if (is_array($options["dropzone"]) && $options["multiple"]) {
            $action = (!empty($options["action"]) ? $options["action"] : ".");
            $view->vars["attr"]["class"] = "dropzone";

            $options["dropzone"] ??= [];
            if (!array_key_exists("url", $options["dropzone"])) {
                $options["dropzone"]["url"] = $action;
            }
            if ($options['allow_delete'] !== null) {
                $options["dropzone"]["addRemoveLinks"] = $options['allow_delete'];
            }
            $options["dropzone"]["maxFilesize"] = $options["max_size"] / 1e6; // from B to MB
            if ($options['max_files'] !== null) {
                $options["dropzone"]["maxFiles"] = $options["max_files"];
            }
            if ($mimeTypes) {
                $options["dropzone"]["acceptedFiles"] = implode(",", $mimeTypes);
            }

            if (!array_key_exists("parallelUploads", $options["dropzone"])) {
                $options["dropzone"]["parallelUploads"] = $options['parallel_uploads'];
            }
            if (!array_key_exists("uploadMultiple", $options["dropzone"])) {
                $options["dropzone"]["uploadMultiple"] = $options['upload_multiple'];
            }

            $options["dropzone"]["thumbnailWidth"] = $options['thumbnail_width'] ?? null;
            $options["dropzone"]["thumbnailHeight"] = $options['thumbnail_height'] ?? null;

            $options["dropzone"]["dictDefaultMessage"] = $options["dropzone"]["dictDefaultMessage"] ?? $this->translator->trans("@fields.fileupload.dropzone.default_message");
            $options["dropzone"]["dictFallbackMessage"] = $options["dropzone"]["dictFallbackMessage"] ?? $this->translator->trans("@fields.fileupload.dropzone.fallback_message");
            $options["dropzone"]["dictFileTooBig"] = $options["dropzone"]["dictFileTooBig"] ?? $this->translator->trans("@fields.fileupload.dropzone.file_too_big");
            $options["dropzone"]["dictInvalidFileType"] = $options["dropzone"]["dictInvalidFileType"] ?? $this->translator->trans("@fields.fileupload.dropzone.invalid_file_type");
            $options["dropzone"]["dictMaxFilesExceeded"] = $options["dropzone"]["dictMaxFilesExceeded"] ?? $this->translator->trans("@fields.fileupload.dropzone.max_files_exceeded");
            $options["dropzone"]["dictResponseError"] = $options["dropzone"]["dictResponseError"] ?? $this->translator->trans("@fields.fileupload.dropzone.response_error");
            $options["dropzone"]["dictCancelUpload"] = $options["dropzone"]["dictCancelUpload"] ?? $this->translator->trans("@fields.fileupload.dropzone.cancel_upload");
            $options["dropzone"]["dictRemoveFile"] = $options["dropzone"]["dictRemoveFile"] ?? $this->translator->trans("@fields.fileupload.dropzone.remove_file");

            if ($options["allow_cancel[confirmation]"]) {
                $options["dropzone"]["dictCancelUploadConfirmation"] = $options["dropzone"]["dictCancelUploadConfirmation"] ?? $this->translator->trans("@fields.fileupload.dropzone.cancel_upload_confirmation");
            }
            if ($options["allow_delete[confirmation]"]) {
                $options["dropzone"]["dictRemoveFileConfirmation"] = $options["dropzone"]["dictRemoveFileConfirmation"] ?? $this->translator->trans("@fields.fileupload.dropzone.remove_file_confirmation");
            }

            if (array_key_exists("maxFiles", $options["dropzone"]) && !empty($view->vars["value"])) {
                $options["dropzone"]["maxFiles"] -= count(explode("|", $view->vars["value"]));
            }

            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();
            $data = $this->obfuscator->encode(array_merge($options["dropzone"], ["token" => $token]), ObfuscatorInterface::USE_SHORT);
            $view->vars["ajax"] = $this->router->generate("ux_dropzone", ["data" => $data]);

            $options["dropzone"]["url"] = $view->vars["ajax"];

            $view->vars["dropzone"] = json_encode($options["dropzone"]);
            $view->vars["sortable"] = json_encode($options["sortable"]);
        }
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
