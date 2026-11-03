<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Field\Behaviour\Slug;
use Base\Service\Model\AutovalidateInterface;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SlugType extends AbstractType implements AutovalidateInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'slug';
    }

    /** The neutral behaviour's options (Base\Field\Behaviour\Slug): strict by default when nothing is kept. */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults((new Slug())->options([]));
        $resolver->setDefault("strict", null);

        $resolver->setNormalizer('strict', function (Options $options, $value) {
            return $value ?? empty($options["keep"] ?? "");
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $vars = (new Slug())->vars($options, $view->vars["value"] ?? null, $view->vars["id"]);
        unset($vars["target"]); // the form's own path, read below with the form
        $view->vars = array_replace($view->vars, $vars);

        // Make sure field is not rquired when slugis nullable
        $dataClass = $form->getParent()->getConfig()->getDataClass();
        if ($dataClass && $this->classMetadataManipulator->hasField($dataClass, $form->getName())) {
            $fieldMapping = $this->classMetadataManipulator->getFieldMapping($dataClass, $form->getName());
            $isNullable = $fieldMapping->nullable ?? false;
            $view->vars["required"] = $options["required"] || !$isNullable;
        }

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
        if(!$target) {
            throw new Exception("Target field not found in `".$form->getName()."`.");
        }

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
