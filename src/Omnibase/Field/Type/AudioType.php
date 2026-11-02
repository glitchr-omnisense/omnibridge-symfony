<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AudioType extends FileType
{
    public function getBlockPrefix(): string
    {
        return 'audioupload';
    }

    public function getParent(): ?string
    {
        return FileType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mime_types' => ["audio/*"],
            'multisource' => true,
            "lightbox" => null
        ]);

        $resolver->setNormalizer('multiple', function (Options $options, $value) {
            return $value === null ? $options["multisource"] : $value;
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        if (!($view->vars["mime_types"] ?? false)) {
            $view->vars["mime_types"] = "audio/*";
        }
    }
}
