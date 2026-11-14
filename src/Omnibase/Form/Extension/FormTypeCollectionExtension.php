<?php

namespace Omnibridge\Symfony\Omnibase\Form\Extension;

use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Omnibridge\Symfony\Omnibase\Form\FormMapping;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\Form\AbstractTypeExtension;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Omnibridge\Symfony\Omnibase\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

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
        if (FormMapping::isPersisted($form->getData()) &&
            FormMapping::of($this->classMetadataManipulator)->isCollectionOwner($form, $form->getData()) === false) {
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
