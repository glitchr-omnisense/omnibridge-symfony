<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\Upload;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoType extends FileType
{
    public const VARIANT = Upload::VIDEO;

    public function getBlockPrefix(): string
    {
        return 'videoupload';
    }

    public function getParent(): ?string
    {
        return FileType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mime_types' => ["video/*"],
            'multisource' => true,
            "lightbox" => null
        ]);

        $resolver->setNormalizer('multiple', function (Options $options, $value) {
            return $value === null ? $options["multisource"] : $value;
        });
    }

    /** FileType's view on this type's variant of the behaviour (Base\Field\Behaviour\Upload). */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }
}
