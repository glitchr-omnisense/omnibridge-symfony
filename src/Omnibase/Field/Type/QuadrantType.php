<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Enum\Quadrant\Quadrant;
use Base\Enum\Quadrant\Quadrant8;

use Twig\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

class QuadrantType extends AbstractType implements DataMapperInterface
{
    public function getBlockPrefix(): string
    {
        return 'quadrant';
    }

    /**
     * @var Environment
     */
    protected Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "class" => Quadrant8::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->add("wind", HiddenType::class);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['positions'] = json_encode($options["class"]::getPositions());
        $view->vars['quadrants'] = $options["class"]::getPermittedValues();
        $view->vars['icons'] = $options["class"]::getIcons();
        $view->vars['default'] = $options["class"]::getDefault();
    }

    /**
     * @param $viewData
     * @param Traversable $forms
     * @return void
     */
    public function mapDataToForms($viewData, Traversable $forms): void
    {
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $windType = current(iterator_to_array($forms));
        $viewData = $windType->getData() ?? Quadrant::O;
    }
}
