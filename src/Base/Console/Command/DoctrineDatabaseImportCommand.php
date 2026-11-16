<?php

namespace Base\Console\Command;

use Base\Console\Report;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Notifier\Notifier;
use Base\Notifier\NotifierInterface;
use Base\Serializer\Encoder\ExcelEncoder;
use Base\Service\LocalizerInterface;
use Base\Service\Model\GraphInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\Common\Collections\Collection;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineDatabaseImport;
use Omnibridge\Doctrine\Omnibase\Console\Task\DoctrineDatabaseImportInput;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

/** doctrine:database:import: its options to omnibridge/doctrine's DoctrineDatabaseImport task (Doctrine-typed) (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
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
        $this->entityManager = $entityManager;
        parent::__construct($localizer, $translator, null !== $entityManager ? UnitOfWorks::of($entityManager) : null, $parameterBag);

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
        $helper = $this->getHelper('question');
        $bar = null;

        return (new DoctrineDatabaseImport($this->entityManager, $this->classMetadataManipulator, $this->entityHydrator, $this->translator, $this->notifier))->run(
            new DoctrineDatabaseImportInput(
                $input->getArgument('path'),
                null !== $input->getOption('spreadsheet') ? explode(',', (string) $input->getOption('spreadsheet')) : null,
                $input->getOption('extension'),
                (int) $input->getOption('entries'),
                (int) $input->getOption('skip'),
                (int) $input->getOption('batch'),
                (bool) $input->getOption('notify'),
                (bool) $input->getOption('force'),
                (bool) $input->getOption('on-the-fly'),
            ),
            Reports::printer($input, $output),
            static fn (string $question): ?string => $helper->ask($input, $output, new Question($question)),
            static fn (string $question, array $choices): array => (array) $helper->ask($input, $output, (new ChoiceQuestion($question, $choices))->setMultiselect(true)),
            static function (string $step, int $total = 0) use (&$bar, $output): void {
                match ($step) {
                    'start' => ($bar = new ProgressBar($output, $total))->start(),
                    'advance' => $bar?->advance(),
                    default => $bar?->finish(),
                };
            },
        )->status();
    }
}
