<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\AssociationFile;
use Base\Contract\Runtime;
use Base\Admin\Controller\AbstractCrudController;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Base\Enum\UserRole;
use Omnibridge\Symfony\Omnibase\Form\FormFactory;
use Base\Service\FileService;
use Base\Service\MediaService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Mapping\MappingException;
use Base\Admin\Config\Action;
use Base\Routing\AdminUrlGeneratorInterface;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Traversable;

class AssociationFileType extends AbstractType implements DataMapperInterface
{
    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    /**
     * @var FormFactory|null
     */
    protected ?FormFactory $formFactory = null;


    /**
     * @var FileService
     */
    protected mixed $fileService = null;

    /**
     * @var EntityHydrator|null
     */
    protected ?EntityHydrator $entityHydrator = null;

    /**
     * @var MediaService|null
     */
    protected ?MediaService $mediaService = null;

    /**
     * @var PropertyAccessor|null
     */
    protected ?PropertyAccessor $propertyAccessor = null;

    /**
     * @var AdminUrlGeneratorInterface|null
     */
    protected ?AdminUrlGeneratorInterface $adminUrlGenerator = null;

    public function getBlockPrefix(): string
    {
        return 'associationfile';
    }

    public function __construct(FormFactory $formFactory, ClassMetadataManipulator $classMetadataManipulator, EntityHydrator $entityHydrator, MediaService $mediaService, AdminUrlGeneratorInterface $adminUrlGenerator)
    {
        $this->formFactory = $formFactory;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityHydrator = $entityHydrator;

        $this->mediaService = $mediaService;
        $this->fileService = cast($mediaService, FileService::class);
        $this->adminUrlGenerator = $adminUrlGenerator;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The type's defaults are the neutral behaviour's (Base\Field\Behaviour\AssociationFile), its upload named by its class here.
        $resolver->setDefaults(array_replace(AssociationFile::DEFAULTS, ['form_type' => FileType::class]));

        $resolver->setNormalizer('data_class', function (Options $options, $value) {
            if ($options["multiple"]) {
                return null;
            }
            return $value ?? null;
        });

        $resolver->setNormalizer('form_type', function (Options $options, $value) {
            // A variant's name (file, image, avatar), as a neutral definition names it: its type.
            $value = [\Base\Field\Behaviour\Upload::IMAGE => ImageType::class, \Base\Field\Behaviour\Upload::AVATAR => AvatarType::class, \Base\Field\Behaviour\Upload::FILE => FileType::class][$value] ?? $value;
            if (is_instanceof($value, FileType::class)) {
                return $value;
            }
            if (is_instanceof($value, ImageType::class)) {
                return $value;
            }
            if (is_instanceof($value, AvatarType::class)) {
                return $value;
            }
            throw new Exception("Option \"form_type\" must inherit from FileType");
        });

        $resolver->setNormalizer('class', function (Options $options, $value) {
            if ($value === null) {
                throw new Exception("Option \"class\" must be defined");
            }
            return $value;
        });

        $resolver->setNormalizer('entity_file', function (Options $options, $value) {
            if ($value === null) {
                throw new Exception("Option \"entity_file\" must be pointing to the file field");
            }
            return $value;
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        //
        // Set controller url
        $options["class"] = $this->formFactory->guessClass($form, $options);
        $options["multiple"] = $this->formFactory->guessMultiple($form, $options);
        $options["sortable"] = $this->formFactory->guessSortable($form, $options);

        $parentForm = $form->getParent();
        $dataClass = $parentForm ? $this->formFactory->guessClass($parentForm, $parentForm->getConfig()->getOptions()) : null;
        $isNullable = $dataClass ? $this->classMetadataManipulator->getMapping($dataClass, $form->getName())["nullable"] ?? false : false;
        $crudController = AbstractCrudController::getCrudControllerFqcn($options["class"]);

        $href = null;
        if ($options["href"] === null && $crudController && Runtime::access()->isGranted(UserRole::ADMIN)) {
            $href = $this->adminUrlGenerator
                ->unsetAll()
                ->setController($crudController)
                ->setAction(Action::EDIT)
                ->setEntityId("{0}")
                ->generateUrl();
        }

        $view->vars["href"] = $options["href"] ?? $href;
        $view->vars["multiple"] = $options["multiple"];
        $view->vars["allow_delete"] = $options["allow_delete"];

        $view->vars["required"] = $options["required"] ?? (!$isNullable && !$this->classMetadataManipulator->isToManySide($dataClass, $form->getName()));

        // Each stored file's name by its record's id (the neutral behaviour's, the same omnibridge/laravel draws).
        $view->vars["entityId"] = AssociationFile::entries($form->getData(), $options);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $data = $event->getData();

            $options["class"] = $this->formFactory->guessClass($event, $options);
            $options["multiple"] = $this->formFactory->guessMultiple($event, $options);
            $options["sortable"] = $this->formFactory->guessSortable($event, $options);

            $baseName = AssociationFile::child($options);

            $parentForm = $form->getParent();
            $dataClass = $parentForm ? $this->formFactory->guessClass($parentForm, $parentForm->getConfig()->getOptions()) : null;
            $isNullable = $dataClass ? $this->classMetadataManipulator->getMapping($dataClass, $form->getName())["nullable"] ?? false : false;

            // The upload field's options and the related records' files: the neutral behaviour's.
            $form->add($baseName, $options["form_type"], AssociationFile::childOptions($options, !$isNullable));
            $form->get($baseName)->setData(AssociationFile::files($data, $options));
        });
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
     * @throws MappingException
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $parentForm = current(iterator_to_array($forms))->getParent();
        if ($parentForm?->getData() instanceof PersistentCollection &&
            $this->classMetadataManipulator->isCollectionOwner($parentForm, $parentForm?->getData()) === false) {
            return;
        }

        $parentEntity = $parentForm->getParent() ? $parentForm->getParent()->getData() : null;

        $options = $parentForm->getConfig()->getOptions();
        $options["data_class"] = $options["data_class"] ?? $this->formFactory->guessClass($parentForm, $options);
        $options["multiple"] = $options["multiple"] ?? $this->formFactory->guessMultiple($parentForm, $options);

        $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["data_class"]);
        if (!$classMetadata) {
            throw new Exception("Entity \"" . $options['data_class'] . "\" not found.");
        }

        $form = iterator_to_array($forms)[AssociationFile::child($options)] ?? null;
        if ($form) {
            // What was sent made into the related records: the neutral behaviour's (records()); written here into this host's collection.
            $records = AssociationFile::records($form->getData(), $viewData, array_replace($options, ['class' => $options['data_class']]), $parentEntity, $this->entityHydrator);
            $newData = new ArrayCollection(AssociationFile::list($records));

            if ($options["multiple"]) {
                if ($viewData instanceof PersistentCollection) {
                    $mappedBy = $viewData->getMapping()["mappedBy"];
                    $fieldName = $viewData->getMapping()["fieldName"];
                    $isOwningSide = $viewData->getMapping()["isOwningSide"];

                    if (!$isOwningSide) {
                        foreach ($viewData as $entry) {
                            $this->propertyAccessor->setValue($entry, $mappedBy, null);
                        }
                    }

                    $viewData->clear();
                    foreach ($newData as $entry) {
                        $viewData->add($entry);
                        if (!$isOwningSide) {
                            $this->propertyAccessor->setValue($entry, $mappedBy, $viewData->getOwner());
                        }
                    }
                } else {
                    $viewData = $newData;
                }
            } elseif ($newData->first()) {
                $viewData = $newData->first();
            }
        }
    }
}
