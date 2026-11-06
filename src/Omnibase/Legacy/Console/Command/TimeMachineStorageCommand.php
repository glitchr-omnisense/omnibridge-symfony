<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use BackupManager\Filesystems\Destination;
use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Storages;
use Base\Service\TimeMachineInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Attribute\AsCommand;

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
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $parameterBag,
        TimeMachineInterface   $timeMachine,
        Storages     $flysystem
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->timeMachine = $timeMachine;
        $this->flysystem = $flysystem;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->section()->writeln("Available storage place(s):");

        foreach ($this->timeMachine->getStorageList() as $storageName => $storage) {
            $output->section()->writeln(" * <info>" . $storageName . "</info> (" . get_class($storage) . ")");
        }

        return Command::SUCCESS;
    }
}
