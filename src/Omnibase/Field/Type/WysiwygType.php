<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class WysiwygType extends AbstractType
{
    /** @var ParameterBagInterface */
    protected $parameterBag;

    /** @var TranslatorInterface */
    protected $translator;

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator)
    {
        $this->parameterBag = $parameterBag;
        $this->translator = $translator;
    }

    public function getParent(): ?string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'wysiwyg';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'empty_data' => null,
            "webpack_entry" => "form.wysiwyg",

            'theme' => "snow",
            'placeholder' => $this->translator->trans("@fields.wysiwyg.placeholder"),
            'height' => "250px",
            'modules' => [
                "syntax" => true,
                "toolbar" => [
                    [
                        ["header" => [1, 2, false]]
                    ],
                    ['bold', 'italic', 'underline', 'strike'],
                    [
                        ['color' => []],
                        ['background' => []],
                        ['script' => 'sub'],
                        ['script' => 'super']
                    ],

                    ['image', 'link', 'blockquote', 'code-block'],

                    [
                        ['list' => 'ordered'],
                        ['list' => 'bullet'],
                        ['align' => []],
                    ],

                    ['clean']
                ]
            ]
        ]);
    }

    /**
     * @param $view
     * @return string|null
     */
    public function getFormID($view): ?string
    {
        $parent = $view->parent;
        while ($parent->parent) {
            $parent = $parent->parent;
        }

        return $parent->vars["attr"]["id"] ?? null;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Wysiwyg options
        $theme = $options["theme"];
        $modules = $options["modules"] ?? [];

        $wysiwygOpts = [];
        $wysiwygOpts["theme"] = $theme;
        $wysiwygOpts["modules"] = $modules;
        $wysiwygOpts["placeholder"] = $options["placeholder"];
        $wysiwygOpts["height"] = $options["height"];

        $view->vars["wysiwyg"] = json_encode($wysiwygOpts);
    }
}
