<?php

namespace Base\Form\Extension;

use Base\Form\FormProxyInterface;
use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FormTypeWebpackExtension extends AbstractTypeExtension
{
    /**
     * @var FormProxyInterface
     */
    protected FormProxyInterface $formProxy;

    /**
     * @var WebpackTagRenderer
     */
    protected WebpackTagRenderer $webpackTagRenderer;

    public function __construct(FormProxyInterface $formProxy, WebpackTagRenderer $webpackTagRenderer)
    {
        $this->formProxy = $formProxy;
        $this->webpackTagRenderer = $webpackTagRenderer;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'webpack_entry' => null
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->browseView($view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        $this->webpackTagRenderer->loadEntry($options["webpack_entry"] ?? null);

        foreach ($view->children as $field => $childView) {

            if (!$form->has($field)) {
                continue;
            }

            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            $this->webpackTagRenderer->loadEntry($childOptions["webpack_entry"] ?? null);

            $this->browseView($childView, $childForm, $childOptions);
        }
    }
}
