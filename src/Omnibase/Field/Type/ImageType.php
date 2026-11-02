<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use InvalidArgumentException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Base\Service\ObfuscatorInterface;
use Base\View\AssetPaths;

class ImageType extends FileType
{
    public function getBlockPrefix(): string
    {
        return 'imageupload';
    }

    public function getParent(): ?string
    {
        return FileType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'thumbnail' => "bundles/base/images/image.svg",
            'clipboard' => true,

            'modal' => [
                "keyboard" => false,
                "backdrop" => "static"
            ],

            'cropper' => null,

            'mime_types' => ["image/*"],
            "inline" => false
        ]);

        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        if ($options["multiple"] && is_array($options["cropper"])) {
            throw new InvalidArgumentException("There can be only one picture if you want to crop, please disable 'multiple' option");
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        if (!($view->vars["mime_types"] ?? false)) {
            $view->vars["mime_types"] = "image/*";
        }

        // The thumbnail's path as the page links it: omnibase's Twig environment answers it (Base\View\AssetPaths).
        $view->vars["thumbnail"] = $this->twig instanceof AssetPaths ? $this->twig->getAsset($options["thumbnail"]) : $options["thumbnail"];
        $view->vars["modal"] = json_encode($options["modal"]);

        $view->vars["cropper"] = null;
        if (is_array($options["cropper"])) {
            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();
            $data = $this->obfuscator->encode(array_merge($options["dropzone"], ["token" => $token]), ObfuscatorInterface::USE_SHORT);
            $view->vars["ajax"] = $this->router->generate("ux_dropzone", ["data" => $data]);

            if (!array_key_exists('viewMode', $options["cropper"])) {
                $options["cropper"]['viewMode'] = 2;
            }
            if (!array_key_exists('autoCropArea', $options["cropper"])) {
                $options["cropper"]['autoCropArea'] = true;
            }
            if (!array_key_exists('movable', $options["cropper"])) {
                $options["cropper"]['movable'] = false;
            }
            if (!array_key_exists('zoomable', $options["cropper"])) {
                $options["cropper"]['zoomable'] = false;
            }
            if (!array_key_exists('rotatable', $options["cropper"])) {
                $options["cropper"]['rotatable'] = false;
            }
            if (!array_key_exists('scalable', $options["cropper"])) {
                $options["cropper"]['scalable'] = false;
            }

            $view->vars["cropper"] = json_encode($options["cropper"]);
        }
    }
}
