<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Console\Command;

use Omnibridge\Symfony\Omnibase\Console\Command;
use Base\Notifier\Notifier;
use Base\Service\Flysystem;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;

#[AsCommand(name: 'cache:clear', aliases: [], description: '')]
class CacheClearCommand extends Command
{
    protected string $projectDir;
    protected string $cacheDir;
    public static string $testFile;

    /** Inner Symfony commands */
    protected SymfonyCommand $cacheClearCommand;
    protected SymfonyCommand $cacheClearSessionsCommand;

    protected Flysystem $flysystem;
    protected Notifier $notifier;
    protected AdvancedRouterInterface $router;


    public function __construct(
        LocalizerInterface      $localizer,
        TranslatorInterface     $translator,
        ?EntityManagerInterface $entityManager,
        ParameterBagInterface   $parameterBag,
        SymfonyCommand          $cacheClearCommand,
        SymfonyCommand          $cacheClearSessionsCommand,
        Flysystem               $flysystem,
        Notifier                $notifier,
        AdvancedRouterInterface $router,
        string                  $projectDir,
        string                  $cacheDir
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->cacheClearCommand = $cacheClearCommand;
        $this->cacheClearSessionsCommand = $cacheClearSessionsCommand;

        $this->flysystem = $flysystem;
        $this->notifier  = $notifier;
        $this->router    = $router;

        $this->projectDir = rtrim($projectDir, '/');
        $this->cacheDir   = rtrim($cacheDir, '/');

        // Old counter file (kept for your existing logic)
        self::$testFile = $this->cacheDir . ".txt";
        self::markAsFirstClear(!file_exists(self::$testFile));
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDefinition([
                new InputOption('sessions', '', InputOption::VALUE_NONE, 'Kill all existing sessions'),
                new InputOption('no-extension', '', InputOption::VALUE_NONE, 'Skip base extension'),
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command clears and warms up the application cache for a given environment
and debug mode:

  <info>php %command.full_name% --env=dev</info>
  <info>php %command.full_name% --env=prod --no-debug</info>
EOF);
    }

    public static function getTestFile(): string { return self::$testFile; }
    public static function markAsFirstClear(bool $first = true) { if ($first) file_put_contents(self::$testFile, 0); }
    public static function applicationNotStarted(): bool { return self::getNClears() < 1; }
    public static function isFirstClear(): bool { return self::getNClears() < 2; }
    public static function getNClears(): int { return file_exists(self::$testFile) ? (int) file_get_contents(self::$testFile) : 0; }
}
