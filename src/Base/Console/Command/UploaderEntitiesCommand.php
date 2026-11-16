<?php

namespace Base\Console\Command;

use Base\Attributes\AttributeReader;
use Base\Console\Task\UploaderEntities;
use Base\Console\Task\UploaderEntitiesInput;
use Base\Database\Attribute\Uploader;
use Base\Database\Repository\Repositories;
use League\Flysystem\FilesystemException;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Omnidrive\Model\Entry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** uploader:entities: its options to omnibase's UploaderEntities task (its plan, step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'uploader:entities', aliases: [], description: '')]
class UploaderEntitiesCommand extends Command
{
    /** The entities of a class named at run time (step R28: Base\Database\Repository\Repositories). */
    protected Repositories $repositories;

    public function setRepositories(Repositories $repositories): static
    {
        $this->repositories = $repositories;

        return $this;
    }

    protected ?string $entityName;
    protected ?string $property;
    protected ?string $uuid;

    protected ?bool $orphans;
    protected ?bool $deleteOrphans;

    protected $propertyAttributes;
    protected $baseEntities;
    protected $baseEntityLocation;
    protected $appEntities;
    protected $appEntityLocation;

    protected function configure(): void
    {
        $this->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
        $this->addOption('property', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific property ?');
        $this->addOption('uuid', null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific uuid ?');

        $this->addOption('orphans', false, InputOption::VALUE_NONE, 'Do you want to get orphans ?');
        $this->addOption('delete-orphans', false, InputOption::VALUE_NONE, 'Do you want to delete orphans ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new UploaderEntities($this->repositories))->run(new UploaderEntitiesInput(
            $this->parameterBag->get('kernel.project_dir').'/src',
            $input->getOption('entity'),
            $input->getOption('property'),
            $input->getOption('uuid'),
            (bool) $input->getOption('orphans'),
            (bool) $input->getOption('delete-orphans'),
        ), Reports::printer($input, $output))->status();
    }

    /**
     * @param string|null $namespace
     * @return array
     * @throws \Exception
     */
    protected function getUploaderAttributes(OutputInterface $output, ?string $namespace)
    {
        $path = "";
        if(str_starts_with($namespace, "App\Entity")) {
        
            $path = BaseBundle::getInstance()->getBundleLocation()."/src/Entity";
        
        } else if(str_starts_with($namespace, "Base\Entity")) {
        
            $path = $this->parameterBag->get("kernel.project_dir")."/src/Entity";
        
        } else {
            
            $msg = ' [ERR] Entity must be located either in `App\Entity` or `Base\Entity`';
            $output->writeln('');
            $output->writeln('<warning,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</warning,bkg>');
            $output->writeln('');

            throw new \Exception();
        }

        $path = $path."/".($this->entityName ? str_replace("\\", "/", $this->entityName).".php" : "");
        $classes = BaseBundle::getInstance()->getAllClasses($path, "");

        $metadataClasses = [];
        foreach ($classes as $class) {
            $metadataClasses[$class] = $this->doctrine()->getClassMetadata($class);
        }

        $attributes = [];
        $attributeReader = AttributeReader::getInstance();
        foreach ($metadataClasses as $class => $classMetadata) {
            $this->propertyAttributes = $attributeReader->getPropertyAttributes($classMetadata, Uploader::class);
            if ($this->propertyAttributes) {
                $attributes[$class] = $this->propertyAttributes;
            }
        }

        return $attributes;
    }

    private $allEntries = [];

    /**
     * @param $class
     * @return array|mixed|object[]
     */
    public function getEntries($class)
    {
        $this->allEntries[$class] ??= $this->repositories->of($class)->findAll();

        return $this->allEntries[$class];
    }

    private $fileList = [];

    /**
     * @param string $class
     * @param string $field
     * @param Uploader $attribute
     * @return array|mixed
     * @throws FilesystemException
     */
    protected function getFileList(string $class, string $field, Uploader $attribute)
    {
        $classPath = dirname($attribute->getPath($class, $field));
        $filesystem = Uploader::getFlysystem();

        $propertyFqcn = $class . "::" . $field;
        if (!array_key_exists($propertyFqcn, $this->fileList)) {
            // The default storage's files under the class's folder, as omnidrive lists them (step R28).
            $this->fileList[$propertyFqcn] = array_values(array_filter(array_map(function (Entry $f) use ($attribute) {
                if (!$f->isFile()) {
                    return null;
                }
                return $attribute->getFlysystem()->getPublic($f->path, $attribute->getStorage());
            }, iterator_to_array($filesystem->getDrive()->list($classPath), false))));
        }

        if ($this->uuid) {
            $this->fileList[$propertyFqcn] = array_filter($this->fileList[$propertyFqcn], fn($f) => basename($f) == $this->uuid);
        }

        return $this->fileList[$propertyFqcn];
    }

    /**
     * @param string $class
     * @param string $field
     * @param Uploader $attribute
     * @return array
     * @throws \Exception
     */
    public function getOrphanFiles(string $class, string $field, Uploader $attribute)
    {
        if ($attribute->getMissable()) {
            return [];
        }

        $fileList = $this->getFileList($class, $field, $attribute);
        $fileListInDatabase = array_map(
            fn($e) => $this->propertyAccessor->getValue($e, $field),
            $this->getEntries($class)
        );

        $injection = array_values(array_diff($fileList, array_flatten(".", $fileListInDatabase)));
        $surjection = array_values(array_diff(array_flatten(".", $fileListInDatabase), $fileList));

        return array_filter(array_unique(array_merge($injection, $surjection)));
    }

    /**
     * @param Uploader $attribute
     * @param array $fileList
     * @return true
     */
    public function deleteOrphanFiles(Uploader $attribute, array $fileList)
    {
        $publicPath = $attribute->getFlysystem()->getPublic("", $attribute->getStorage());
        $filesystem = Uploader::getFlysystem();

        foreach ($fileList as $file) {
            $filesystem->delete(str_lstrip(realpath($file), realpath($publicPath)));
        }
        return true;
    }


    public function preProcess(mixed $class, string $field, Uploader $attribute)
    {
    }

    public function postProcess(mixed $class, string $field, Uploader $attribute, array $fileList)
    {
    }

    /** Doctrine's entity manager, through the unit of work the command is given (Doctrine's on a Doctrine host). */
    private function doctrine(): EntityManagerInterface
    {
        $unitOfWork = $this->getUnitOfWork();
        if (!$unitOfWork instanceof DoctrineUnitOfWork) {
            throw new \LogicException('The uploader attributes are read from Doctrine\'s metadata (base.persistence: doctrine).');
        }

        return $unitOfWork->entityManager();
    }
}
