<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cache:clear:sessions', aliases: [], description: '')]
class CacheClearSessionsCommand extends Command
{
    /** @var string */
    protected string $projectDir;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        EntityManagerInterface   $entityManager,
        ParameterBagInterface    $parameterBag,
        string                   $projectDir,
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sessionDir = $this->projectDir . '/var/sessions';
        if (is_dir($sessionDir)) {
            
            $files = glob($sessionDir . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        $io->write("<info> [INFO] Session files cleared from ".$sessionDir.".</info>" . PHP_EOL, true);
        return Command::SUCCESS;
    }
}
