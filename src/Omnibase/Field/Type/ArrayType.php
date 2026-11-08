<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Base\Field\Behaviour\Collection;
use Base\Service\TranslatorInterface;
use Base\Routing\AdminUrlGeneratorInterface;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

class ArrayType extends CollectionType
{
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    /**
     * The service definition inherits CollectionType's args (`->parent(...)`)
     * and APPENDS its own - so this signature must mirror the parent's
     * positionally. When the parent gained a RequestStack, this ctor received
     * it as its 5th argument where it expected the manipulator, and every
     * form using ArrayType 500'd (caught by the groups/new render test).
     */
    public function __construct(TranslatorInterface $translator, AuthorizationChecker $authorizationChecker, AdminUrlGeneratorInterface $adminUrlGenerator, ?\Symfony\Component\HttpFoundation\RequestStack $requestStack = null, ?ClassMetadataManipulator $classMetadataManipulator = null)
    {
        parent::__construct($translator, $authorizationChecker, $adminUrlGenerator, $requestStack);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string
    {
        return 'array';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // omnisense/omnibase's Collection: ArrayType's defaults, the same on every host (its entry here a Symfony type).
        $resolver->setDefaults(array_replace(Collection::ARRAY_DEFAULTS, ['entry_type' => TextType::class]));

        $resolver->setNormalizer('target', function (Options $options, $value) {
            if ($options["pattern"] !== null && $value !== null) {
                throw new Exception("Option \"target\" cannot be set at the same time as \"pattern\"");
            }
        });

        $resolver->setNormalizer('length', fn(Options $options, $value) => Collection::length(['pattern' => $options["pattern"], 'length' => $value]));
        $resolver->setNormalizer('allow_add', fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete', fn(Options $options, $value) => $options["length"] == 0 && $value);
    }

    public function buildFormArray(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $data = $event->getData() ?? [];
            $event->setData(array_values($data));
        });

        parent::buildForm($builder, $options);
    }

    public function buildFormAssociativeArray(FormBuilderInterface $builder, array $options)
    {
        $data = [];
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$data) {
            $data = $event->getData();
            $event->setData([]);
        });
        parent::buildForm($builder, $options);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$data) {
            $event->setData($data);
        });

        $prototypeOptions = $options['entry_options'];
        if (null !== $options['prototype_data']) {
            $prototypeOptions['data'] = $options['prototype_data'];
        }

        if (null !== $options['entry_required']) {
            $prototypeOptions['required'] = $options['entry_required'];
        }

        $prototypeOptions["attr"]['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
        $prototype = $builder->create($options['prototype_name'], FormType::class, ["label" => false])
            ->add("key", TextType::class, ["label" => false, "attr" => ["placeholder" => $this->translator->trans("@fields.array.key")]])
            ->add("value", $options['entry_type'], $prototypeOptions);

        $builder->setAttribute('prototype', $prototype->getForm());
        $builder->add($prototype);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options, $builder, $prototypeOptions) {
            $form = $event->getForm();
            $data = $event->getData();

            $data = array_transforms(fn($key, $entry): array => [null, [
                "key" => is_array($entry) ? ($entry["key"] ?? null) : $key,
                "value" => is_array($entry) ? ($entry["value"] ?? first($entry) ?? null) : $entry,
            ]], $data);

            $event->setData($data);

            foreach ($data ?? [] as $id => $element) {
                $form->add(
                    $builder->create($id, FormType::class, ["label" => false, 'auto_initialize' => false])
                        ->add("key", TextType::class, ["label" => false, "attr" => ["placeholder" => $this->translator->trans("@fields.array.key")]])
                        ->add("value", $options['entry_type'], $prototypeOptions)->getForm()
                );
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options, $builder, $prototypeOptions) {
            $form = $event->getForm();
            $data = $event->getData();

            foreach ($data ?? [] as $id => $element) {
                $form->add(
                    $builder->create($id, FormType::class, ["label" => false, 'auto_initialize' => false])
                        ->add("key", TextType::class, ["label" => false, "attr" => ["placeholder" => $this->translator->trans("@fields.array.key")]])
                        ->add("value", $options['entry_type'], $prototypeOptions)->getForm()
                );
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use (&$options, $builder, $prototypeOptions) {
            $event->setData(
                array_transforms(fn($k, $v): array => [
                    first($v) ?? null,
                    second($v) ?? null
                ], $event->getData())
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['associative']) {
            $this->buildFormAssociativeArray($builder, $options);
        } else {
            $this->buildFormArray($builder, $options);
        }
    }

    /**
     * @param $pattern
     * @return int
     */
    public function getNumberOfArguments($pattern): int
    {
        return preg_match_all('/\{[0-9]*\}/i', $pattern);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);

        $view->vars['length'] = is_string($options["length"]) ? explode(".", $options["length"]) : $options["length"];
        $view->vars["pattern"] = $options["pattern"];
        $view->vars["placeholder"] = $options["placeholder"];
        $view->vars["associative"] = $options['associative'];
    }
}
