<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Task\TimeMachineStorage;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Storages;
use Base\Service\TimeMachineInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** timemachine:storage: its options to omnibase's TimeMachineStorage task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'timemachine:storage', aliases: [], description: '')]
class TimeMachineStorageCommand extends Command
{
    /**
     * @var TimeMachineInterface
     */
    protected $timeMachine;
    protected Storages $flysystem;

    public function __construct(
        LocalizerInterface     $localizer,
        TranslatorInterface    $translator,
        ?UnitOfWork $unitOfWork,
        ParameterBagInterface  $parameterBag,
        TimeMachineInterface   $timeMachine,
        Storages     $flysystem
    )
    {
        parent::__construct($localizer, $translator, $unitOfWork, $parameterBag);
        $this->timeMachine = $timeMachine;
        $this->flysystem = $flysystem;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new TimeMachineStorage($this->timeMachine))->run(Reports::printer($input, $output))->status();
    }
}
