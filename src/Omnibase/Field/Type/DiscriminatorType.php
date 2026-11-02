<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Controller\Admin\AbstractDashboardController;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Symfony\Omnibase\Form\FormFactory;
use Base\Service\Model\Autocomplete;
use Base\Service\Model\IconizeInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DiscriminatorType extends AbstractType
{
    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    /**
     * @var FormFactory|null
     */
    protected ?FormFactory $formFactory = null;

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string
    {
        return 'discriminator';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "exclude_root" => true,
            "discriminator_autoload" => false
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $form = $event->getForm();

            $class = $options["data_class"] ?? $this->formFactory->guessClass($form->getParent());
            if (!$class) {
                throw new Exception("Entity cannot be determined for " . $form->getName());
            }

            $discriminatorMap = $this->classMetadataManipulator->getDiscriminatorMap($class);
            $rootEntityName = $this->classMetadataManipulator->getRootEntityName($class);

            if ($options["discriminator_autoload"]) {
                $choices = [];
                foreach ($discriminatorMap as $key => $entity) {
                    $icon = null;
                    if (class_implements_interface($entity, IconizeInterface::class)) {
                        $icon = $entity::__iconizeStatic()[0];
                    }

                    if ($options["exclude_root"] && $entity == $rootEntityName) {
                        continue;
                    }

                    $choices[mb_ucwords($key)] = [$icon, $key];
                }

                $form->add("choice", SelectType::class, ["choices" => $choices]);
            }
        });
    }

    /**
     * @param $entry
     * @param $class
     * @param TranslatorInterface|null $translator
     * @param $format
     * @return array
     */
    public static function getFormattedValues($entry, $class = null, ?TranslatorInterface $translator = null, $format = FORMAT_TITLECASE)
    {
        $entry = implode(".", array_unique(explode(".", $entry)));

        $formattedValues = (new Autocomplete($translator))->resolve($entry, $class, ["format" => $format]);
        $formattedValues["icon"] = class_implements_interface($class, IconizeInterface::class) ? $class::__iconizeStatic()[0] ?? null : null;

        $text = $translator->trans($entry . "." . Translator::NOUN_SINGULAR, [], AbstractDashboardController::TRANSLATION_ENTITY);
        switch ($format) {
            case FORMAT_TITLECASE:
                $text = mb_ucwords(mb_strtolower($text));
                break;

            case FORMAT_SENTENCECASE:
                $text = mb_ucfirst(mb_strtolower($text));
                break;

            case FORMAT_LOWERCASE:
                $text = mb_strtolower($text);
                break;

            case FORMAT_UPPERCASE:
                $text = mb_strtoupper($text);
                break;
        }

        $formattedValues["text"] = $text;
        return $formattedValues;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //Optional: Implement SelectType + AssociationType with autoload option if option enabled
    }
}
