<?php

namespace Base\Field\Type;

use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends NumberType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'stock';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['allow_infinite'] = $options["allow_infinite"];
        $view->vars["stepUp"] = $options["stepUp"];
        $view->vars["stepDown"] = $options["stepDown"];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'allow_infinite' => true,
            'stepUp' => 1,
            'stepDown' => 1,
            'target' => null
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Check if path is reacheable..
        if ($options["target"] !== null && str_starts_with($options["target"], ".")) {
            $view->vars["ancestor"] = $view->parent;

            $target = $form->getParent();
            $targetPath = substr($options["target"], 1);
        } else {
            // Get oldest parent form available..
            $ancestor = $view;
            while ($ancestor->parent !== null) {
                $ancestor = $ancestor->parent;
            }

            $view->vars["ancestor"] = $ancestor;

            $target = $form->getParent();
            while ($target && ($target->getViewData() instanceof Collection || $target->getViewData() === null)) {
                $target = $target->getParent();
            }

            $targetPath = $options["target"];
        }

        $targetPath = $targetPath ? explode(".", $targetPath) : null;
        foreach ($targetPath ?? [] as $path) {
            if (!$target->has($path)) {
                throw new Exception("Child form \"$path\" related to view data \"" . get_class($target->getViewData()) . "\" not found in " . get_class($form->getConfig()->getType()->getInnerType()) . " (complete path: \"" . $options["target"] . "\")");
            }

            $target = $target->get($path);
            $targetType = $target->getConfig()->getType()->getInnerType();

            if ($targetType instanceof TranslationType) {
                $availableLocales = array_keys($target->all());
                $locale = (count($availableLocales) > 1 ? $targetType->getDefaultLocale() : $availableLocales[0] ?? null);
                if ($locale) {
                    $target = $target->get($locale);
                }
            }
        }

        $view->vars['target'] = $targetPath;
    }
}
