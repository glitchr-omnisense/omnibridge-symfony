<?php

namespace Base\Console\Command;

use Base\Console\Task\EntityDiscriminator;
use Base\Console\Task\EntityDiscriminatorInput;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Exception;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** entity:discriminator: its options to omnibase's EntityDiscriminator task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'entity:discriminator', aliases: [], description: '')]
class EntityDiscriminatorCommand extends Command
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
        $this->addArgument('entity', InputArgument::REQUIRED, 'Which entity should be considered ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new EntityDiscriminator($this->unitOfWork))->run(new EntityDiscriminatorInput((string) $input->getArgument('entity')), Reports::printer($input, $output))->status();
    }
}
