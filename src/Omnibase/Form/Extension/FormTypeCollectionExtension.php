<?php

namespace Omnibridge\Symfony\Omnibase\Form\Extension;

use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FormTypeCollectionExtension extends AbstractTypeExtension
{
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->browseView($view, $form, $options);
    }

    public function browseView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getData() instanceof PersistentCollection &&
            $this->classMetadataManipulator->isCollectionOwner($form, $form->getData()) === false) {
            $view->vars["required"] = false;
            $view->vars["disabled"] = true;
        }

        foreach ($view->children as $field => $childView) {
            if (!$form->has($field)) {
                continue;
            }

            $childForm = $form->get($field);
            $childOptions = $childForm->getConfig()->getOptions();

            $this->browseView($childView, $childForm, $childOptions);
        }
    }
}
