<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use InvalidArgumentException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvatarType extends ImageType
{
    public function getBlockPrefix(): string
    {
        return 'avatar';
    }

    public function getParent(): ?string
    {
        return ImageType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'thumbnail' => "bundles/base/images/user.svg",
            "lightbox" => null,
            'clipboard' => false,
            'cropper' => null,
            "inline" => true
        ]);

        $resolver->setNormalizer('cropper', function (Options $options, $value) {
            if (is_array($value) && !array_key_exists("aspectRatio", $value)) {
                $value["aspectRatio"] = 1;
            }
            return $value;
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options["multiple"]) {
            throw new InvalidArgumentException("There can be only one picture for avatar type, please disable 'multiple' option");
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['avatar'] = $view->vars['files'][0] ?? null;
        $view->vars['files'] = [];
    }
}
