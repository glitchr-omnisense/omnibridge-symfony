<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Task\TimeMachineSnapshot;
use Base\Console\Task\TimeMachineSnapshotInput;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Storages;
use Base\Service\TimeMachineInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** timemachine:snapshot: its options to omnibase's TimeMachineSnapshot task (omnibase's plan "omnibase agnostic", step F5e, slice 3), the Report printed. */
#[AsCommand(name: 'timemachine:snapshot', aliases: [], description: '')]
class TimeMachineSnapshotCommand extends Command
{
    /**
     * @var TimeMachineInterface
     */
    protected $timeMachine;

    /**
     * @var Storages
     */
    protected $flysystem;

    /**
     * @var string
     */
    protected $environment;

    public function __construct(
        LocalizerInterface     $localizer,
        TranslatorInterface    $translator,
        ?UnitOfWork $unitOfWork,
        ParameterBagInterface  $parameterBag,
        TimeMachineInterface   $timeMachine,
        Storages     $flysystem
    )
    {
        $this->environment = $parameterBag->get("kernel.environment");
        $this->timeMachine = $timeMachine;
        $this->flysystem = $flysystem;

        parent::__construct($localizer, $translator, $unitOfWork, $parameterBag);
    }

    protected function configure(): void
    {
        $this->addArgument('storages', InputArgument::IS_ARRAY, 'What storages do you want to backup?');

        $this->addOption('cycle', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
        $this->addOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Which prefix do you want to use?', $this->environment);
        $this->addOption('database', null, InputOption::VALUE_OPTIONAL, 'Which database do you want to backup?', null);    
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->timeMachine->setCommandOutput($output);

        return (new TimeMachineSnapshot($this->timeMachine, $this->flysystem))->run(new TimeMachineSnapshotInput((array) $input->getArgument('storages'), $input->getOption('database'), $input->getOption('prefix'), (int) ($input->getOption('cycle') ?? -1)), Reports::printer($input, $output))->status();
    }
}
