<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\Upload;
use InvalidArgumentException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageType extends FileType
{
    public const VARIANT = Upload::IMAGE;

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
        $resolver->setDefaults(Upload::VARIANTS[Upload::IMAGE]);

        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        if ($options["multiple"] && is_array($options["cropper"])) {
            throw new InvalidArgumentException("There can be only one picture if you want to crop, please disable 'multiple' option");
        }
    }

    /** FileType's view on this type's variant of the behaviour (Base\Field\Behaviour\Upload). */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }
}
