<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Base\BaseBundle;

use Base\Database\UnitOfWorks;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Base\Service\Model\AutocompleteInterface;

use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

#[AsCommand(name: 'autocomplete:entities', aliases: [], description: '')]
class AutocompleteEntitiesCommand extends Command
{
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        ?EntityManagerInterface  $entityManager,
        ParameterBagInterface    $parameterBag,
        ClassMetadataManipulator $classMetadataManipulator
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Which entity should I pick up ?');
        $this->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Which class should I pick up?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = \Base\BaseBundle::getBundleDir().'/src';

        $entityId = $input->getOption('id') ?? null;
        $entityClass = $input->getOption('entity') ?? "";

        if ($entityClass) {
            if (!$this->classMetadataManipulator->isEntity($entityClass)) {
                throw new Exception("Entity \"$entityClass\" doesn't exists");
            }
            if (!class_implements_interface($entityClass, AutocompleteInterface::class)) {
                throw new Exception("Entity \"$entityClass\" doesn't implement " . AutocompleteInterface::class);
            }

            $repository = UnitOfWorks::repositories($this->unitOfWork)->of($entityClass);
            $entities = $entityId ? $repository->findBy(["id" => $entityId]) : $repository->findAll();
            if (empty($entities)) {
                if ($entityId) {
                    throw new Exception($entityClass . " #" . $entityId . " not found.");
                } else {
                    throw new Exception("No " . $entityClass . " found.");
                }
            }

            $maxLength = 0;
            foreach ($entities as $entity) {
                $autocomplete = $entity->__autocomplete();
                $maxLength = max(strlen($autocomplete), $maxLength);
            }

            foreach ($entities as $entity) {
                $autocomplete = $entity->__autocomplete();
                $autocompleteData = $entity->__autocompleteData();
                if ($autocomplete) {
                    $autocompleteData = trim(str_replace(["\t", "\n"], ["", " "], print_r($autocompleteData, true)));
                }

                $space = str_repeat(" ", max($maxLength - strlen($autocomplete), 0));

                $output->section()->writeln("<info>" . $entityClass . " #" . $entity->getId() . "</info>; <warning>Autocomplete = </warning>\"" . $autocomplete . "\"" . $space . "<warning> / Data = </warning>\"$autocompleteData\"");
            }
        } else {

            $entity = array_filter(array_merge(
                BaseBundle::getInstance()->getAllClasses($baseLocation . "/Entity"),
                BaseBundle::getInstance()->getAllClasses("./src/Entity"),
            ), fn($c) => class_implements_interface($c, AutocompleteInterface::class));

            if ($entity) {
                $output->section()->writeln("Entity candidate list: " . $entityClass);
            }

            foreach ($entity as $_entity) {
                $output->section()->writeln(" * <info>" . $_entity . "</info>");
            }
        }

        return Command::SUCCESS;
    }
}
