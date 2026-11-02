<?php

namespace Omnibridge\Symfony\Omnibase\Console\Command;

use Base\Traits\CacheClearTrait;
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
    use CacheClearTrait;

    protected string $projectDir;
    protected string $cacheDir;
    public static string $testFile;

    /** Inner Symfony commands */
    protected SymfonyCommand $cacheClearCommand;
    protected SymfonyCommand $cacheClearSessionsCommand;

    protected Flysystem $flysystem;
    protected Notifier $notifier;
    protected AdvancedRouterInterface $router;

    /** --- Locking --- */
    private string $lockFile;
    /** @var resource|null */
    private $lockHandle = null;
    private int $lockTtl = 120; // seconds

    public function __construct(
        LocalizerInterface      $localizer,
        TranslatorInterface     $translator,
        EntityManagerInterface  $entityManager,
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

        // New lock file lives under var/cache/
        $this->lockFile = $this->cacheDir . '/.lock';
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

    /** Acquire non-blocking lock with stale eviction */
    private function acquireLock(OutputInterface $out): void
    {
        // Ensure cache dir exists
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }

        // Drop stale lock (mtime older than TTL)
        if (is_file($this->lockFile)) {
            clearstatcache(true, $this->lockFile);
            $age = time() - (int) @filemtime($this->lockFile);
            if ($age > $this->lockTtl) {
                @unlink($this->lockFile);
            }
        }

        // Open lock file and try to take an exclusive, non-blocking lock
        $h = @fopen($this->lockFile, 'c+'); // 'c+' is atomic create/open without truncation
        if ($h === false) {
            throw new \RuntimeException("Cannot open lock file: {$this->lockFile}");
        }

        // Try to lock without blocking
        if (!@flock($h, LOCK_EX | LOCK_NB)) {
            // Someone else holds it. If it's become stale meanwhile, evict and retry once.
            clearstatcache(true, $this->lockFile);
            $age = time() - (int) @filemtime($this->lockFile);
            if ($age > $this->lockTtl && @unlink($this->lockFile)) {
                // recreate and lock
                @fclose($h);
                $h = @fopen($this->lockFile, 'c+');
                if ($h === false || !@flock($h, LOCK_EX | LOCK_NB)) {
                    throw new \RuntimeException('Failed to acquire lock after evicting stale lock.');
                }
            } else {
                $out->writeln('<comment>Another cache:clear is already running; skip.</comment>');
                // Do not treat as an error; just exit SUCCESS to avoid restart flapping
                // but we must close the handle we opened
                @fclose($h);
                // Signal to caller by throwing a special exception? Simpler: store null and throw.
                throw new \LogicException('LOCK_HELD'); // handled in execute()
            }
        }

        // We own the lock
        // Write small metadata (pid + time) and touch mtime to keep it fresh during long clears
        @ftruncate($h, 0);
        @fwrite($h, json_encode([
            'pid' => getmypid(),
            'time' => time(),
            'host' => gethostname(),
        ]));
        @fflush($h);
        @touch($this->lockFile);

        $this->lockHandle = $h;

        // Keep mtime fresh during long operations (optional, harmless if pcntl not available)
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
        // Best-effort: refresh mtime on shutdown so it doesn't look stale mid-flight
        register_shutdown_function(function () {
            if (is_file($this->lockFile)) {
                @touch($this->lockFile);
            }
        });
    }

    /** Always release lock */
    private function releaseLock(): void
    {
        if ($this->lockHandle) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
            if (is_file($this->lockFile)) {
                @unlink($this->lockFile);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->cacheClearCommand->setApplication($this->getApplication());

        // --- Lock guard (skip if someone else is running) ---
        try {

            $this->acquireLock($output);

        } catch (\LogicException $e) {
            
            if ($e->getMessage() === 'LOCK_HELD') {
                // Change this line to fail or colorize differently
                $output->writeln('<error>Cache clear aborted: another process is already clearing the cache.</error>');
                return SymfonyCommand::SUCCESS;
            }
            throw $e;
        }

        try {

            $noExtension = $input->getOption('no-extension') ?? true;
            if (!$noExtension) {
                $this->phpConfigCheck($io);
                $this->diskAndMemoryCheck($io);
                $this->checkCache($io);
                $this->checkVirtualization($io);
                $this->checkExtensions($io);
                $this->customFeatureWarnings($io);
            }

            $noWarmup = $input->getOption('no-warmup');
            $noOptionalWarmers = $input->getOption('no-optional-warmers') || $noWarmup;
            $io->writeln($noOptionalWarmers
                ? "\n // Optional cache warmers disabled."
                : "\n // <info>All</info> cache warmers requested."
            );

            // What reads the application's services runs before the cache goes: once it is cleared,
            // a service this container has not built yet cannot be built any more (its file was in
            // the folder just removed) - the technical recipient's check and the phpinfo/symlinks
            // ended every cache:clear with "Failed opening required .../get...Service.php", from
            // a warm cache, debug or not (lesringostars' CacheLifecycleTest, master plan §5
            // invariant 3). The second clear runs in a process of its own, on the new cache.
            if (!$noExtension) {
                $this->webpackCheck($io);
                $this->technicalSupportCheck($io);
                $this->generatePhpInfo($io, !$this->router->isDebug());
                if (!self::applicationNotStarted()) {
                    $this->generateSymlinks($io);
                }
            }

            $ret = $this->cacheClearCommand->execute($input, $output);

            self::markAsFirstClear(!file_exists(self::$testFile));
            file_put_contents(self::$testFile, self::getNClears() + 1);

            if ($input->getOption('sessions')) {
                $this->cacheClearSessionsCommand->execute($input, $output);
            }

            if (!$noExtension) {
                $this->doubleCacheClear($io);
                $this->clearOPCache($io);
            }

            return $ret;

        } finally {

            $this->releaseLock();
        }
    }
}