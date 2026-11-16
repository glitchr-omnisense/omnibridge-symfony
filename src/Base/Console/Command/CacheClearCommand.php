<?php

namespace Base\Console\Command;

use Base\Console\Task\CacheClear;
use Base\Console\Task\CacheClearInput;
use Base\Notifier\Notifier;
use Base\Service\Flysystem;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Base\Database\Mapping\ClassMetadataFactory;
use Base\Console\Command;
use Base\Console\Reports;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * cache:clear: omnibase's CacheClear task (its plan, step F5e, slice 3: the lock, the checks, the counter of
 * clears, the second clear) around FrameworkBundle's own cache:clear, the Report printed between what it prints.
 * The counter's static questions (getNClears(), isFirstClear()...) stay this class's, on the same file.
 */
#[AsCommand(name: 'cache:clear', aliases: [], description: '')]
class CacheClearCommand extends Command
{
    protected ?EntityManagerInterface $entityManager;

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
        $this->entityManager = $entityManager;
        parent::__construct($localizer, $translator, null !== $entityManager ? UnitOfWorks::of($entityManager) : null, $parameterBag);
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cacheClearCommand->setApplication($this->getApplication());

        return (new CacheClear($this->parameterBag, $this->flysystem, $this->notifier))->run(
            new CacheClearInput(
                $this->projectDir,
                $this->cacheDir,
                checks: !$input->getOption('no-extension'),
                optionalWarmers: !$input->getOption('no-optional-warmers') && !$input->getOption('no-warmup'),
                sessions: (bool) $input->getOption('sessions'),
                phpInfo: $this->router->isDebug(),
                hostFallback: (bool) $this->router->getHostFallback(),
                customMetadataFactory: $this->entityManager->getMetadataFactory() instanceof ClassMetadataFactory,
            ),
            fn (): int => $this->cacheClearCommand->execute($input, $output),
            fn () => (new Process(['php', 'bin/console', 'cache:clear']))->setWorkingDirectory($this->projectDir)->mustRun(),
            fn () => $this->cacheClearSessionsCommand->execute($input, $output),
            Reports::printer($input, $output),
        )->status();
    }
}
