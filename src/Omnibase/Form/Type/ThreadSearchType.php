<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\ThreadSearchModel;
use Symfony\Component\Form\Extension\Core\Type\SearchType;

class ThreadSearchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ThreadSearchModel::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('generic', SearchType::class, ["required" => false]);
        $builder->add('content', SearchType::class, ["required" => false]);
        $builder->add('title', SearchType::class, ["required" => false]);
        $builder->add('excerpt', SearchType::class, ["required" => false]);
        // $builder->add('content', SelectType::class, [ // Webpack required
        //     "required" => false,
        //     "class" => Destination::class
        // ]);
    }
}
