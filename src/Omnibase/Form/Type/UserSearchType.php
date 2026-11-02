<?php

namespace Omnibridge\Symfony\Omnibase\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractType;
use Base\Form\Model\UserSearchModel;

/**
 * The members' search (user_search): a name to look for. It had no field at
 * all, so its page could not render the input its template asked for.
 */
class UserSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', SearchType::class, ['required' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserSearchModel::class
        ]);
    }
}
