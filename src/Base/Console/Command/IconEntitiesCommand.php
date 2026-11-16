<?php

namespace Base\Console\Command;

use Base\Console\Task\EntitiesInput;
use Base\Console\Task\IconEntities;
use Base\Database\UnitOfWorks;
use Base\Service\LocalizerInterface;
use Base\Service\Model\IconizeInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Exception;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use Base\Database\Mapping\MetadataManipulator as ClassMetadataManipulator;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Base\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

/** icon:entities: its options to omnibase's IconEntities task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'icon:entities', aliases: [], description: '')]
class IconEntitiesCommand extends Command
{
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        ?UnitOfWork  $unitOfWork,
        ParameterBagInterface    $parameterBag,
        ClassMetadataManipulator $classMetadataManipulator
    )
    {
        parent::__construct($localizer, $translator, $unitOfWork, $parameterBag);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Should I pick up one specific entityId ?');
        $this->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new IconEntities($this->unitOfWork))->run(new EntitiesInput($this->parameterBag->get('kernel.project_dir').'/src', $input->getOption('entity') ?: null, $input->getOption('id') ?: null), Reports::printer($input, $output))->status();
    }
}
