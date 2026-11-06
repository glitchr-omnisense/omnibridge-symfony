<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Base\Database\Repository\Repositories;
use Base\Database\Attribute\Uploader;
use Base\Attributes\AttributeReader;
use Base\BaseBundle;
use Omnidrive\Model\Entry;
use Omnibridge\Symfony\Omnibase\Console\Command;

use League\Flysystem\FilesystemException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

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
        $this->entityName ??= str_strip($input->getOption('entity'), ["App\\Entity\\", "Base\\Entity\\"]);
        $this->property ??= $input->getOption('property');
        $this->uuid ??= $input->getOption('uuid');

        $this->orphans ??= $input->getOption('orphans');
        $this->deleteOrphans ??= $input->getOption('delete-orphans');

        $output->section()->writeln("\n <info>Looking for \"" . Uploader::class . "\"</info> attributes..");

        $nTotalFiles = 0;
        $nTotalOrphans = 0;
        $nTotalFields = 0;

        $this->appEntities ??= "App\\Entity\\" . $this->entityName;
        $appAttributes = $this->getUploaderAttributes($output, $this->appEntities);
        if (!$appAttributes) {
            $output->section()->writeln("\t<warning>Uploader attribute not found for \"$this->appEntities\"</warning>");
        }

        $this->baseEntities ??= "Base\\Entity\\" . $this->entityName;
        $baseAttributes = $this->getUploaderAttributes($output, $this->baseEntities);
        if (!$baseAttributes) {
            $output->section()->writeln("\t<warning>Uploader attribute not found for \"$this->baseEntities\"</warning>");
        }

        $output->section()->writeln("", OutputInterface::VERBOSITY_VERBOSE);

        $attributes = array_merge($appAttributes, $baseAttributes);
        foreach ($attributes as $class => $_) {
            if (!str_starts_with($class, "Base\\Entity\\" . $this->entityName) && !str_starts_with($class, "App\\Entity\\" . $this->entityName)) {
                continue;
            }

            $noPropertyFound = true;
            foreach ($_ as $field => $attribute) {
                if ($this->property && $field != $this->property) {
                    continue;
                }
                $nTotalFields++;

                $attribute = array_end($attribute);
                if ($attribute->getDeclaringEntity($class, $field) != $class) {
                    continue;
                }

                if ($attribute->getMissable()) {
                    $output->section()->writeln("             $class::$field <warning> is missable.. cannot have orphan files..</warning>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    continue;
                } else {
                    $output->section()->writeln("             Processing <info>$class::$field</info>..");
                }

                $this->preProcess($class, $field, $attribute);

                $publicPath = $attribute->getFlysystem()->getPublic("", $attribute->getStorage());
                $fileList = $this->getFileList($class, $field, $attribute);
                $nTotalFiles += count($fileList);
                $noPropertyFound = false;

                if ($this->uuid) {
                    $output->section()->writeln("\t     $class::$field <ln>UUID \"$this->uuid\" found.</ln>", OutputInterface::VERBOSITY_VERBOSE);
                } else {
                    $output->section()->writeln("\t     $class::$field <ln>" . count($fileList) . " file(s) found.</ln>", OutputInterface::VERBOSITY_VERBOSE);
                }

                foreach ($fileList as $file) {
                    $output->section()->writeln("\t           <ln>* ." . str_lstrip(realpath($file), realpath($publicPath)) . "</ln>", OutputInterface::VERBOSITY_DEBUG);
                }

                if ($this->orphans || $this->deleteOrphans) {
                    $orphanFiles = $this->getOrphanFiles($class, $field, $attribute);
                    $nOrphans = count($orphanFiles);
                    $nTotalOrphans += $nOrphans;

                    $output->section()->writeln("\t           <info>Looking for orphan files</info> in $publicPath <warning>$nOrphans orphan file(s) found.</warning>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    foreach ($orphanFiles as $file) {
                        $output->section()->writeln("\t           <warning>* ." . str_lstrip(realpath($file), realpath($publicPath)) . "</warning>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    }

                    if ($this->deleteOrphans) {
                        $this->deleteOrphanFiles($attribute, $orphanFiles);

                        if ($orphanFiles) {
                            $output->section()->writeln("\t           <red>* Orphan files deleted..</red>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                        } else {
                            $output->section()->writeln("\t           <warning>* No orphan files to be deleted..</warning>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                        }
                    }
                }

                $this->postProcess($class, $field, $attribute, $fileList);
            }

            if ($noPropertyFound && !$this->property) {
                $output->section()->writeln("             $class::$field <warning>not declared in this class..</warning>", OutputInterface::VERBOSITY_DEBUG);
                $nTotalFields--;
            }
        }

        $orphanStr = null;
        if ($this->orphans) {
            $orphanStr = '; ' . $nTotalOrphans . ' orphan(s)';
        }

        $msg = ' [OK] ' . $nTotalFields . ' fields found: ' . $nTotalFiles . ' file(s)' . $orphanStr . ' ! ';
        $output->writeln('');
        $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
        $output->writeln($msg);
        $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');
        $output->writeln('');

        return Command::SUCCESS;
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
            $metadataClasses[$class] = $this->entityManager->getClassMetadata($class);
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
}
