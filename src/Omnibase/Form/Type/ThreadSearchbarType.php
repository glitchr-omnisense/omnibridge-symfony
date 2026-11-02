<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\ThreadSearchModel;

class ThreadSearchbarType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ThreadSearchModel::class,
            'parent' => null
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('parent_id', HiddenType::class);
        $builder->add('generic', SearchType::class);
    }
}
