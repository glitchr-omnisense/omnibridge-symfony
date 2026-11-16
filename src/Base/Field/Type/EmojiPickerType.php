<?php

namespace Base\Field\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormBuilderInterface;

final class EmojiPickerType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'emojipickr';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'renderer' => "native", // native, twemoji, (custom?)
            'is_nullable' => true,
            'webpack_entry' => "form.emoji"
        ]);
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']["class"] = "form-emoji";

        $view->vars["renderer"] = $options["renderer"];
    }
}
