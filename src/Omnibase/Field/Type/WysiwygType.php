<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\Wysiwyg;
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

    /** The neutral behaviour's options (Base\Field\Behaviour\Wysiwyg): Quill's theme, toolbar, placeholder, height. */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults((new Wysiwyg())->options([]));
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

    /** The widget's id and Quill's settings, as the neutral behaviour gives them to every host. */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, (new Wysiwyg())->vars($options, $view->vars['value'] ?? null, $view->vars['id']));
    }
}
