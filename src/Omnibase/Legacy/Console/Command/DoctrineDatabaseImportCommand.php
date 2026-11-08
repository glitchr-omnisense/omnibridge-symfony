<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Database\Entity\EntityHydratorInterface;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Notifier\NotifierInterface;
use Base\Serializer\Encoder\ExcelEncoder;

use Base\Database\Entity\EntityHydrator;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;

use Base\Service\Model\GraphInterface;
use Base\Notifier\Notifier;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(name: 'doctrine:database:import', aliases: [], description: 'This command allows to import data from an XLS file into the database')]
class DoctrineDatabaseImportCommand extends Command
{
    public const OFFSET = 2;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var EntityHydratorInterface
     */
    protected EntityHydratorInterface $entityHydrator;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public const UNKNOWN_STATE = -1;
    public const VALID_ENTITY = 0;
    public const FIELD_REQUIRED = 1;
    public const PARENT_NOT_FOUND = 2;
    public const ALREADY_IN_DATABASE = 3;

    /**
     * @var NotifierInterface
     */
    protected NotifierInterface $notifier;

    /**
     * @var Serializer
     */
    protected Serializer $serializer;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        ?EntityManagerInterface  $entityManager,
        ParameterBagInterface    $parameterBag,
        EntityHydrator           $entityHydrator,
        ClassMetadataManipulator $classMetadataManipulator,
        Notifier                 $notifier
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);

        $this->entityHydrator = $entityHydrator;
        $this->notifier = $notifier;

        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->classMetadataManipulator->setGlobalTrackingPolicy(ClassMetadata::CHANGETRACKING_DEFERRED_EXPLICIT);
        $this->serializer = new Serializer([], [
            new XmlEncoder(),
            new CsvEncoder(),
            new ExcelEncoder()
        ]);
    }

    /**
     * @param $entityName
     * @param $entityData
     * @return array|null
     * @throws Exception
     */
    public function normalize($entityName, $entityData)
    {
        return array_inflate(".", array_transforms(function ($propertyPath, $entry, $fn, $i) use ($entityName): ?array {
            $propertyPath = trim(explode('\n', $propertyPath)[0]); // Only keeps headline
            $fieldName = explodeByArray([":", "["], $propertyPath)[0];
            if (preg_match('/(.*)\:explode\((.*)\)(.*)/', $propertyPath, $matches)) {
                $propertyPath = $matches[1] . $matches[3] ?? "";
                $entrySeparator = str_split($matches[2]);

                if (!is_array($entry)) {
                    $entry = array_filter(array_map(
                        fn($v) => trim(str_strip(trim($v), $entrySeparator)),
                        explodeByArray($entrySeparator, str_strip(trim($entry), $entrySeparator))
                    ));
                }
            }

            $propertyName = preg_replace("/\:[^\.]*/", "", $propertyPath);
            if (substr_count($propertyName, "[") > 1) {
                throw new Exception("Backets \"[]\" are expected to appear only once at the end.. \"" . $propertyName . "\"");
            }

            if (preg_match('/(.+)(?:\[(.*?)\])(?:\((.)\))*(.*)/', $propertyPath, $matches)) {
                $propertyPath = str_replace(["[", "]"], [".", ""], $matches[1] . $matches[4] ?? "");
                $propertyName = $matches[2] ?? null;
                $fieldSeparator = $matches[3] ?? null;

                $subFieldName = preg_replace("/\:[^\.]*/", "", $propertyPath);
                $entityMapping = $this->classMetadataManipulator->fetchEntityMapping($entityName, $subFieldName);
                $classMetadata = $this->entityManager->getClassMetadata($entityName);

                if (!$classMetadata->hasAssociation($this->classMetadataManipulator->getFieldName($classMetadata, $subFieldName))) {
                    throw new Exception("Field \"" . $subFieldName . "\" is expected to be an association.");
                }

                $isToManySide = in_array($entityMapping["type"], [ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY], true);
                if (!is_array($entry)) {
                    $entry = [$propertyName => $entry];
                } elseif ($isToManySide) {
                    foreach ($entry as &$e) {
                        if (!$fieldSeparator) {
                            $e = [$propertyName => $e];
                        } else {
                            $propertyNames = explode(",", $propertyName);
                            $entries = $e ? explode($fieldSeparator, $e, count($propertyNames)) : [];

                            $e = [];
                            foreach ($propertyNames as $i => $p) {
                                $e[trim($p)] = array_key_exists($i, $entries) && $entries[$i] ? trim($entries[$i]) : null;
                            }
                        }
                    }
                }
            }

            if (is_array($entry)) {
                $entry = array_inflate(".", $entry);
                if ($fieldName != $propertyName) {
                    $entry = array_transforms($fn, [$propertyPath => $entry]);
                    $propertyPath = array_key_first($entry);

                    $entry = first($entry);
                }
            }

            return $propertyPath ? [$propertyPath, $entry] : null;
        }, $entityData));
    }


    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path or URL');

        $this->addOption('spreadsheet', null, InputOption::VALUE_OPTIONAL, 'Import selected spreadsheet (e.g. 1,3,4 [NB: starts from 1])');
        $this->addOption('extension', null, InputOption::VALUE_OPTIONAL, 'Specify file extension to be used');
        $this->addOption('entries', null, InputOption::VALUE_OPTIONAL, 'Only read N-rows', null);
        $this->addOption('skip', null, InputOption::VALUE_OPTIONAL, 'Skip i-entries', 0);
        $this->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Persist by batch of X entries.', 25);
        $this->addOption('notify', null, InputOption::VALUE_NONE, 'Send notification if needed');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Import without asking confirmation.');
        $this->addOption('on-the-fly', null, InputOption::VALUE_NONE, 'Import right after each single entity got hydrated');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = null;
        if ($input->hasArgument("path")) {
            $path = $input->getArgument("path");
        }

        $notify = $input->getOption("notify");
        if (!$notify) {
            $this->notifier->disable();
        }

        $force = $input->getOption("force");
        $extension = $input->getOption("extension");
        $batch = $input->getOption("batch");
        $onTheFly = $input->getOption("on-the-fly");
        $spreadsheets = $input->getOption("spreadsheet") !== null ? explode(",", $input->getOption("spreadsheet")) : null;

        $entries = (int)$input->getOption("entries");
        $skip = (int)$input->getOption("skip");

        if (!$entries) {
            $entries = null;
        }

        $output->writeln("");
        if ($path) {
            $output->writeln(' <info>You have just selected:</info> ' . $path);
        } else {
            $helper = $this->getHelper('question');
            $question = new Question(' > ');

            $output->writeln(' <info>Please enter the location of your file (either local path or url)</info>: ');
            $path = $helper->ask($input, $output, $question);
        }
        $output->writeln("");

        $output->writeln(' Decoding spreadsheets..');
        $mimeType = mime_content_type2($path) ?? "";
        $mimeTypes = new MimeTypes();

        $extension = $extension ?? $mimeTypes->getExtensions($mimeType)[0] ?? null;
        switch ($extension) {
            case "xml":
            case "xls":
            case "xlsx":
            case "xlsm":
                $rawData = $this->serializer->decode(file_get_contents2($path), $extension);
                break;

            case "csv":
            case "txt":
                // One sheet, named after the file: the rows are read as a spreadsheet's are.
                $rawData = [pathinfo((string) parse_url($path, \PHP_URL_PATH), \PATHINFO_FILENAME) ?: 'csv' => $this->serializer->decode(file_get_contents2($path), 'csv')];
                break;

            default:
                throw new Exception("File extension couldn't be determine.. Please use \"--extension\" option.");
        }

        //
        // Default case if no selected spreadsheet
        if ($spreadsheets === null) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(' <info>Please choose which spreadsheet to process </info>: (separated with ,)', array_keys($rawData));
            $question->setMultiselect(true);

            $spreadsheets = array_map(fn($i) => array_search($i, array_keys($rawData)), $helper->ask($input, $output, $question));
        }

        //
        // Import required spreadsheets
        $baseClass = [];

        $entities = [];
        $entityData = [];

        $entityStates = [];
        $entityUniqueKeys = [];
        $entityRequiredFields = [];

        // Shrink and restrict according to options
        $spreadsheetKeys = [];
        if ($spreadsheets !== null) {
            $spreadsheetKeys = array_filter(array_keys($rawData), fn($id) => !in_array($id, $spreadsheets), ARRAY_FILTER_USE_KEY);
        }

        $rawData = array_key_removes($rawData, ...$spreadsheetKeys);
        foreach ($rawData as $spreadsheet => $_) {
            // Remove comments: 2nd line
            array_shift($rawData[$spreadsheet]);
        }

        // Process spreadsheet
        $output->writeln(' Normalizing rows..');
        $totalData = 0;
        $counter = 0;
        foreach ($rawData as $spreadsheet => $entry) {
            $parentThread = null;

            $entities[$spreadsheet] = [];
            $entityData[$spreadsheet] = [];

            $entityStates[$spreadsheet] = [];
            $entityUniqueKeys[$spreadsheet] = [];
            $entityRequiredFields[$spreadsheet] = [];

            //
            // Import type
            $baseName = array_key_first($entry[0] ?? []);
            $baseClass[$spreadsheet] = $baseName;
            if (!$this->classMetadataManipulator->isEntity($baseName)) {
                $output->section()->writeln(" <warning>* Spreadsheet \"$spreadsheet\" is ignored, no valid entity found</warning>\n");
                continue;
            }

            $entities[$spreadsheet][$baseName] ??= [];
            $entityData[$spreadsheet][$baseName] ??= [];

            $entityStates        [$spreadsheet][$baseName] ??= [];
            $entityUniqueKeys    [$spreadsheet][$baseName] ??= [];
            $entityRequiredFields[$spreadsheet][$baseName] ??= [];

            $discriminatorMap = $this->entityManager->getClassMetadata($baseName)->discriminatorMap ?? [];

            if (empty($entityRequiredFields[$spreadsheet][$baseName])) {
                foreach ($entry as $_) {
                    foreach ($_ as $fieldPath => $__) {
                        if (!str_contains($fieldPath, ":unique")) {
                            continue;
                        }

                        $fieldName = explodeByArray([".", "[", ":"], $fieldPath)[0];
                        if (!in_array($fieldName, $entityRequiredFields[$spreadsheet][$baseName])) {
                            $entityRequiredFields[$spreadsheet][$baseName][] = $fieldName;
                        }
                    }
                }
            }

            //
            // Clean up empty fields
            $rawData[$spreadsheet] = array_filter_recursive($entry, fn($d) => $d !== null);

            // Process them
            foreach ($rawData[$spreadsheet] as &$data) {
                if ($counter < $skip || ($entries !== null && $counter > $entries + $skip - 1)) {
                    $counter++;
                    continue;
                }

                if ($discriminatorMap) {
                    $discriminatorEntry = $data[$baseName] ?? array_flip($discriminatorMap)[$baseName];
                    $entityName = $discriminatorMap[$data[$baseName] ?? array_flip($discriminatorMap)[$baseName]] ?? null;

                    if (is_a($baseName, first($discriminatorMap))) {
                        throw new Exception("Entity \"" . $baseName . "\" doesn't inherit from \"" . first($discriminatorMap) . "\"");
                    } elseif (!array_key_exists($discriminatorEntry, $discriminatorMap)) {
                        throw new Exception("Discriminatory entry \"" . $discriminatorEntry . "\" not found in the discriminator list of \"" . first($discriminatorMap) . "\".. something wrong ?");
                    }
                }

                unset($data[$baseName]);

                $entityData[$spreadsheet][$baseName][] = [$entityName, $this->normalize($entityName, $data)];
                $totalData++;
                $counter++;
            }
        }

        $output->writeln(' Hydrating entities..' . ($onTheFly ? " and import them on the fly" : null));
        $progressBar = $totalData ? new ProgressBar($output, $totalData) : null;

        $entityParentColumn = null;
        $entityUniqueValues = [];

        $counter = 0;
        foreach ($rawData as $spreadsheet => $__) {
            foreach ($entityData[$spreadsheet] ?? [] as $baseName => &$entries) {
                $output->writeln("\n * <info>Spreadsheet \"" . $spreadsheet . "\"</info>: $baseName");

                //
                // Loop over entries
                foreach ($entries as $iEntry => &$_) {
                    $progressBar?->advance();
                    $counter++;

                    $keyDepth = [];
                    $targetEntity = [];
                    $inDatabase = false;

                    list($entityName, $entry) = $_;

                    $entry = array_transforms(function ($k, $v, $fn, $i, $d) use ($iEntry, $spreadsheet, &$entityParentColumn, &$entityUniqueValues, $entityName, &$entityUniqueKeys, &$targetEntity, &$keyDepth): ?array {
                        list($fieldName, $special) = array_pad(explode(":", $k, 2), 2, null);
                        $keyDepth[$d] = $fieldName;

                        $fieldPath = implode(".", $keyDepth);
                        $targetName = $this->classMetadataManipulator->fetchEntityName($entityName, $fieldPath);
                        $targetEntity[$d] = $targetName;

                        if (is_array($v)) {
                            $v = array_transforms($fn, $v, $d + 1);
                        }

                        if (!$v && $v !== false) {
                            array_pop($keyDepth);
                            array_pop($targetEntity);

                            return $d > 2 ? null : [$fieldName, $v];
                        }

                        if (class_implements_interface($entityName, GraphInterface::class) && $this->classMetadataManipulator->getFieldName($entityName, $fieldName) == "parent") {
                            $entityParentColumn = $fieldName;
                        }

                        if ($special) {
                            $resolvedFieldPath = $this->classMetadataManipulator->resolveFieldPath($entityName, $fieldPath);
                            if ($resolvedFieldPath === null) {
                                throw new Exception("Cannot resolve field path \"$fieldPath\" for \"$entityName\" for entry #" . ($iEntry + self::OFFSET + 1));
                            }

                            $fieldName = explode(".", $resolvedFieldPath);
                            $fieldName = end($fieldName);

                            $targetName = $targetEntity[$d - 1] ?? $entityName;
                            if (!array_key_exists($targetName, $entityUniqueKeys[$spreadsheet])) {
                                $entityUniqueKeys[$spreadsheet][$targetName] = [];
                            }

                            $mapping = $this->classMetadataManipulator->getMapping($targetName, $fieldName);
                            if ($targetName) {
                                if (!array_key_exists($targetName, $entityUniqueValues)) {
                                    $entityUniqueValues[$targetName] = [];
                                }
                                if (!array_key_exists($fieldName, $entityUniqueValues[$targetName])) {
                                    $entityUniqueValues[$targetName][$fieldName] = [];
                                }

                                switch (explode("(", $special)[0]) {
                                    case "unique":

                                        $entityUniqueKeys[$spreadsheet][$entityName][] = [$targetName, $resolvedFieldPath];
                                        $entityUniqueValues[$targetName][$fieldName][] = $v;
                                        break;

                                    case "find":

                                        if ($this->classMetadataManipulator->hasAssociation($targetName, $fieldName)) {
                                            $targetRepository = $this->entityManager->getRepository($mapping["targetEntity"]);
                                            $isToOneSide = in_array($mapping["type"], [ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE], true);

                                            $v = array_filter($v);
                                            if (empty($v)) {
                                                $v = null;
                                            } else {
                                                $vBak = $v;
                                                if ($isToOneSide) {
                                                    $v = $targetRepository->findBy($vBak);
                                                    if (count($v) > 1) {
                                                        throw new Exception("Failed to parse \"" . $targetName . "\";\nduplicate entity returned with \"" . $fieldName . "\" for entry #" . ($iEntry + self::OFFSET + 1) . "\n(did you forgot to explode the input string ? double check case ?)");
                                                    }

                                                    $v = first($v);
                                                } else {
                                                    $v = array_filter(array_map(function ($e) use ($fieldName, $targetName, $targetRepository, $iEntry) {
                                                        if (!is_array($e)) {
                                                            throw new Exception("Failed to parse \"" . $targetName . "\";\nunexpected value \"" . serialize($e) . "\" for entry #" . ($iEntry + self::OFFSET + 1) . "\n(did you forgot to explode the input string ? double check case ?)");
                                                        }

                                                        $e = array_filter($e);
                                                        if (empty($e)) {
                                                            throw new Exception("Failed to find entity for \"" . $targetName . "::$fieldName\";\nunexpected value \"" . serialize($e) . "\" for entry #" . ($iEntry + self::OFFSET + 1) . "\n(did you forgot to explode the input string ? double check case ?)");
                                                        }

                                                        $findBy = $targetRepository->findBy($e);
                                                        if (empty($findBy)) {
                                                            throw new Exception("Failed to find entity for \"" . $targetName . "::$fieldName\";\ninput was \"" . serialize($e) . "\" for entry #" . ($iEntry + self::OFFSET + 1) . "\n(did you forgot to explode the input string ? double check case ?)");
                                                        }

                                                        return first($findBy);
                                                    }, $vBak));
                                                }

                                                if ($v === null) {
                                                    throw new Exception("Failed to find \"" . $targetName . "\" with parameters \"" . serialize($vBak) . "\" for entry #" . ($iEntry + self::OFFSET + 1));
                                                }
                                            }
                                        }

                                        break;

                                    case "enum":

                                        $special = str_lstrip($special, "enum(");
                                        $enumType = substr($special, 0, strlen($special) - 1);

                                        $typeOfField = $this->classMetadataManipulator->getTargetClass($targetName, $fieldName);
                                        if (!is_instanceof($typeOfField, $enumType)) {
                                            throw new Exception("Incompatibility between EnumType provided \"" . $enumType . "\" and database type \"" . $typeOfField . "\" for entry #" . ($iEntry + self::OFFSET + 1));
                                        }

                                        if (is_instanceof($enumType, SetType::class)) {
                                            $v = array_map(function ($k) use ($enumType, $iEntry) {
                                                $value = $enumType::getValue($k);
                                                if (!$value) {
                                                    throw new Exception("Invalid enum key \"" . $k . "\" provided for EnumType \"" . $enumType . "\" for entry #" . ($iEntry + self::OFFSET + 1));
                                                }
                                                return $value;
                                            }, $v);
                                        } elseif (is_instanceof($enumType, EnumType::class)) {
                                            $v = $enumType::getValue($v);
                                            if (!$v) {
                                                throw new Exception("Invalid enum key provided for EnumType \"" . $enumType . "\" for entry #" . ($iEntry + self::OFFSET + 1));
                                            }
                                        } else {
                                            throw new Exception("Class must be either \"" . EnumType::class . "\" or \"" . SetType::class . "\" for entry #" . ($iEntry + self::OFFSET + 1));
                                        }

                                        break;
                                }
                            }
                        }

                        array_pop($keyDepth);
                        array_pop($targetEntity);

                        return $v !== null ? [$fieldName, $v] : null;
                    }, $entry);

                    if ($entry) {
                        $state = self::VALID_ENTITY;
                        foreach ($entityRequiredFields[$spreadsheet][$baseName] as $field) {
                            if (!array_key_exists($field, $entry)) {
                                $state = self::FIELD_REQUIRED;
                            }
                        }

                        $hydrationOptions = EntityHydrator::CLASS_METHODS | EntityHydrator::OBJECT_PROPERTIES;
                        if ($state == self::FIELD_REQUIRED) {
                            $hydrationOptions |= EntityHydrator::PREVENT_ASSOCIATIONS;
                        }
                        $entity = $this->entityHydrator->hydrate($entityName, $entry, [], $hydrationOptions);

                        //
                        // Check if duplicates found in the processed list
                        foreach ($entityUniqueKeys[$spreadsheet][$entityName] ?? [] as $_) {

                            list($targetName, $resolvedFieldPath) = $_;
                            $fieldPath = explode(".", $resolvedFieldPath);

                            // Extract target entity
                            $targetValue = [$entity];
                            foreach ($fieldPath as $fieldName) {
                                $targetValue = array_flatten('.', array_map(function ($e) use ($fieldName) {
                                    $e = array_filter($this->entityHydrator->dehydrate($e))[$fieldName] ?? null;
                                    if ($e instanceof Collection) {
                                        $e = $e->toArray();
                                    }

                                    return $e;
                                }, $targetValue), PHP_INT_MAX, ARRAY_FLATTEN_PRESERVE_KEYS);
                            }

                            // Check out database
                            $targetRepository = $this->entityManager->getRepository($targetName);
                            $targetFieldName = end($fieldPath);
                            $targetValue = array_values($targetValue);

                            if ($this->classMetadataManipulator->hasAssociation($targetName, $targetFieldName)) {
                                $inDatabase |= ($targetRepository->findOneBy($targetValue) !== null);
                            } elseif ($this->classMetadataManipulator->hasField($targetName, $targetFieldName)) {
                                $inDatabase |= ($targetRepository->findOneBy([$targetFieldName => $targetValue]) !== null);
                            }
                        }

                        if ($inDatabase) {
                            $state = self::ALREADY_IN_DATABASE;
                        } elseif ($this->classMetadataManipulator->hasRecursiveAssociation($entity, $entityParentColumn)) {
                            if ($this->propertyAccessor->getValue($entity, $entityParentColumn)) {
                                $parentThread = $entity;
                            } else {
                                $this->propertyAccessor->setValue($entity, $entityParentColumn, $parentThread);
                                if (!in_array_object($parentThread, $entities[$spreadsheet][$baseName])) {
                                    $state = self::PARENT_NOT_FOUND;
                                }
                            }
                        }

                        $entities[$spreadsheet][$baseName][] = $entity;
                        $entityStates[$spreadsheet][$baseName][] = $state;

                        if ($onTheFly && $state == self::VALID_ENTITY) {
                            try {
                                $this->entityManager->persist($entity);
                                if ($counter && ($counter % $batch) == 0) {
                                    $this->entityManager->flush();
                                    $this->entityManager->clear();
                                }

                                $counter++;
                            } catch (Exception $e) {
                                $msg = " Failed to write " . $this->translator->transEntity($entity) . "(" . $entity . ") for entry #" . ($iEntry + self::OFFSET + 1);
                                $output->writeln('');
                                $output->writeln('');
                                $output->writeln('<red,bkg>' . str_blankspace(strlen($msg)));
                                $output->writeln($msg);
                                $output->writeln(str_blankspace(strlen($msg)) . '</red,bkg>');

                                throw $e;
                            }
                        }
                    }
                }
            }

            //
            // Check if duplicates found in the entity data list
            foreach ($entityUniqueValues as $targetName => $_) {
                foreach ($_ as $uniqueKey => $values) {
                    $occurences = array_filter(array_count_values($values), fn($v) => $v > 1);
                    $duplicates = !empty($occurences);
                    if ($duplicates) {
                        throw new Exception("Duplicate entries \"" . implode(",", array_unique(array_keys($occurences))) . "\" found for $targetName::$" . $uniqueKey . "(for entry #" . ($iEntry + self::OFFSET + 1));
                    }
                }
            }
        }

        $progressBar?->finish();
        $output->writeln('');
        $output->writeln('');

        $totalNewEntries = 0;
        $output->writeln(' <info>New data found: </info>' . implode(", ", array_map(function ($spreadsheet) use (&$totalNewEntries, $baseClass, $entityData, $entityStates) {
                $countData = 0;
                foreach (array_keys($entityData[$spreadsheet]) as $baseName) {
                    $countData += count($entityData[$spreadsheet][$baseName] ?? []);
                }

                $countNewData = 0;
                foreach (array_keys($entityStates[$spreadsheet]) as $baseName) {
                    $countNewData += count(array_filter($entityStates[$spreadsheet][$baseName] ?? [], fn($s) => $s == self::VALID_ENTITY));
                }

                $totalNewEntries += $countNewData;

                $count = $countData > 0 ? $countNewData . "/" . $countData : "0";
                $plural = ($countNewData > 1);

                return $count . " <ln>" . lcfirst($this->translator->transEntity($baseClass[$spreadsheet], null, $plural ? Translator::NOUN_PLURAL : Translator::NOUN_SINGULAR)) . '</ln>';
            }, array_keys(array_filter($entityData)))));

        foreach ($entityData as $spreadsheet => $___) {
            foreach ($___ as $baseName => $__) {
                $baseNameStr = $this->translator->transEntity($baseName);

                $output->writeln("\n * <info>Spreadsheet \"" . $spreadsheet . "\"</info>: $baseName", OutputInterface::VERBOSITY_VERBOSE);

                $totalEntries = count($entities[$spreadsheet][$baseName]);
                foreach ($entities[$spreadsheet][$baseName] ?? [] as $i => $entity) {
                    $state = $entityStates[$spreadsheet][$baseName][$i] ?? self::UNKNOWN_STATE;
                    $spacer = str_blankspace(strlen($totalEntries) - strlen($i + 1));
                    $entityStr = $this->translator->transEntity($entity);

                    switch ($state) {
                        case self::VALID_ENTITY:
                            $output->writeln("\t" . $baseNameStr . " #" . ($i + $skip + 1) . $spacer . " / <ln>" . $entityStr . ": </ln><info>\"" . $entity . "\"</info> " . ($onTheFly ? " just got imported on the fly" : "is ready for import") . " !");
                            break;

                        case self::ALREADY_IN_DATABASE:
                            $output->writeln("\t" . $baseNameStr . " #" . ($i + $skip + 1) . $spacer . " / <warning>" . $entityStr . ": </warning> \"" . $entity . "\" already exists in database", OutputInterface::VERBOSITY_VERBOSE);
                            break;

                        default:

                            if ($state == self::FIELD_REQUIRED) {
                                $msg = "required field missing";
                            } elseif ($state == self::PARENT_NOT_FOUND) {
                                $msg = "parent not found";
                            } else {
                                $msg = "unknown reason";
                            }

                            $output->writeln("\t" . $baseNameStr . " #" . ($i + $skip + 1) . $spacer . " / <red>" . $entityStr . ": </red>\"" . $entity . "\" is invalid  <warning>(hint:\"" . $msg . "\")</warning> and cannot be imported", OutputInterface::VERBOSITY_VERBOSE);
                    }
                }
            }
        }

        $output->writeln("");

        if ($totalNewEntries == 0) {
            $msg = ' [OK] Nothing to update - your database is already in sync with the current dataset. ';
            $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');

            $output->section()->writeln("");
            return Command::SUCCESS;
        }

        if (!$onTheFly) {
            if ($force) {
                $apply = "y";
            } else {
                $helper = $this->getHelper('question');
                $question = new Question(' > ');

                $output->writeln(' <info>Do you want to import these entries into the database ? (yes/no)</info> [<warning>no</warning>]: ');
                $apply = $helper->ask($input, $output, $question);

                if ($apply === null || (strtolower($apply) != "y" && strtolower($apply) != "yes")) {
                    return Command::FAILURE;
                }
            }

            $counter = 0;
            $progressBar = new ProgressBar($output, $totalNewEntries);
            foreach ($entities as $spreadsheet => $_) {
                foreach ($_ as $baseName => $entries) {
                    foreach ($entries as $i => $entity) {
                        $state = $entityStates[$spreadsheet][$baseName][$i] ?? self::UNKNOWN_STATE;
                        if ($state !== self::VALID_ENTITY) {
                            continue;
                        }

                        $progressBar->advance();
                        try {
                            $this->entityManager->persist($entity);
                            if ($counter && ($counter % $batch) == 0) {
                                $this->entityManager->flush();
                            }

                            $counter++;
                        } catch (Exception $e) {
                            $msg = " Failed to write " . $this->translator->transEntity($entity) . "(" . $entity . ")  ";
                            $output->writeln('');
                            $output->writeln('');
                            $output->writeln('<red,bkg>' . str_blankspace(strlen($msg)));
                            $output->writeln($msg);
                            $output->writeln(str_blankspace(strlen($msg)) . '</red,bkg>');

                            throw $e;
                        }
                    }
                }

                $this->entityManager->flush();
            }

            $progressBar->finish();
        }

        $output->section()->writeln("\n\n Updating database...");
        $output->section()->writeln("\n\t <info>" . $totalNewEntries . "</info> entries were added\n");

        $msg = ' [OK] Database content imported successfully! ';
        $output->writeln('<info,bkg>' . str_blankspace(strlen($msg)));
        $output->writeln($msg);
        $output->writeln(str_blankspace(strlen($msg)) . '</info,bkg>');

        $output->section()->writeln("");
        return Command::SUCCESS;
    }
}
