<?php

namespace Omnibridge\Symfony\Omnibase;

if(!isset($_SERVER["APP_TIMER"])) {
    $_SERVER["APP_TIMER"] = microtime(true);
}

use App\Entity\User;
use Omnibridge\Doctrine\Omnibase\Configuration;
use Omnibridge\Doctrine\Omnibase\Types;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

use Base\Attributes\AttributeReader;
use Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseBundle;
use Base\Contract\Runtime;
use Omnibridge\Symfony\Omnibase\Console\Command\CacheClearCommand;
use Base\Demo\DemoGuard;
use Base\Demo\DemoMode;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\DiscoveryPass;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\EnumerationsPass;
use Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\ContractsPass;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Dumper\CliDumper;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Dumper\HtmlDumper;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouter;
use Base\Service\Locator;
use Base\Traits\SingletonTrait;

// The mapping the form and field types are typed on, an alias (Database\MetadataManipulator: the core's on 5.x,
// Doctrine's on 4.x; plan "omnibase agnostic", step B3) - defined before any service is made: a type check never
// autoloads, so an alias not yet loaded would refuse the very object it names.
class_exists(Database\MetadataManipulator::class);

class BaseBundle extends AbstractBaseBundle
{
    // Re-declaring the trait here (already present on AbstractBaseBundle)
    // gives BaseBundle its OWN $_instance storage instead of sharing the
    // abstract parent's - see AbstractBaseBundle's constructor for the
    // full explanation. Every other concrete bundle extending
    // AbstractBaseBundle (Base\Admin\AdminBundle, Base\Wikidoc\WikidocBundle)
    // must do the same.
    use SingletonTrait;

    public const VERSION = '1.0.0';

    public function __construct()
    {
        if (!$this->hasInstance()) {
            self::$_instance = $this;
        }

        // omnibase under Symfony stands on omnibridge/symfony (plan "omnibase
        // agnostique", A1): the adapters of the host's contracts over Symfony's
        // services, their wiring and the framework packages. The core only
        // suggests it (a Laravel site does not want it): said here, before
        // anything of it is asked, rather than as a class not found.
        if (!class_exists(\Omnibridge\Symfony\OmnibridgeBundle::class)) {
            throw new \LogicException('omnisense/omnibase under Symfony needs omnibridge/symfony 1.x (the adapters of the host\'s contracts, Omnibridge\\Symfony\\*, and the framework packages): composer require omnibridge/symfony:1.x-dev');
        }

        // Before the container is built: an error Symfony's ErrorHandler renders
        // by itself (a container that does not compile) links to the host too.
        \Omnibridge\Symfony\FileLinks::export();
    }

    /**
     * The core's folder (vendor/omnisense/omnibase): its src/, templates/, translations/ and
     * resources. The bundle moved to omnibridge/symfony at the plan "omnibase agnostic"'s step F6;
     * what an application, a bundle or a configuration read from its folder is still the core's.
     */
    public static function getBundleDir(): string
    {
        return \dirname((new \ReflectionClass(\Base\Services::class))->getFileName(), 2);
    }

    /**
     * Symfony's path of the bundle (@BaseBundle/..., its templates and translations): the core's
     * src/, as before the move.
     */
    public function getPath(): string
    {
        return self::getBundleDir() . '/src';
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->container->getParameter('kernel.cache_dir');
    }

    /**
     * @return string
     */
    public function getPublicDir(): string 
    {
        return $this->getProjectDir() . "/public";
    }

    /**
     * @return string
     */
    public function getSourceDir(): string
    {
        return $this->getProjectDir() . "/src";
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->container->getParameter('kernel.environment');
    }

    protected bool $boot = false;

    /**
     * @return bool
     */
    public function isBooted()
    {
        return $this->boot;
    }

    protected bool $doctrineReadiness = false;
    public function isDoctrineReady(): bool
    {
        return $this->doctrineReadiness;
    }

    //
    // Some subscribers are not called when modifying codes.
    // The purpose of this broken cache feature is to prevent running without these subscribers
    protected bool $invalidCache = true; // Turned off in subscribers if everything fine.

    /**
     * @return bool
     */
    public function isInvalid(): bool
    {
        return $this->invalidCache;
    }

    public function markCacheAsValid(): void
    {
        $this->invalidCache = false;
    }

    public function warmUp()
    {
        $needsWarmup = !file_exists($this->getCacheDir() . "/pools/base/bundle.php");
        self::$cache               = new PhpArrayAdapter($this->getCacheDir() . "/pools/base/bundle.php", new FilesystemAdapter("", 0, $this->getCacheDir() . "/pools/base/fallback"));
        self::$files               = self::$files               ?? self::$cache->getItem('base.files')->get() ?? [];
        self::$classes             = self::$classes             ?? self::$cache->getItem('base.classes')->get() ?? [];
        self::$aliasList           = self::$aliasList           ?? self::$cache->getItem('base.alias_list')->get() ?? [];
        self::$aliasRepositoryList = self::$aliasRepositoryList ?? self::$cache->getItem('base.alias_repository_list')->get() ?? [];
        self::$aliasDeclared       = self::$aliasDeclared       ?? self::$cache->getItem('base.alias_declared')->get();

        // A pool file that exists but carries no aliases is not a warm cache,
        // it is a cache being (re)written by a concurrent request - seen live
        // as `Class "App\Repository\Thread\MentionRepository" not found` when two
        // requests rebuilt the container at once. Rescan rather than boot
        // with no aliases at all.
        if (!$needsWarmup && empty(self::$aliasList) && empty(self::$aliasRepositoryList)) {
            $needsWarmup = true;
        }

        // warmUp() runs more than once in a process (the dev kernel reboots after
        // a cache rebuild), and class_alias() on an alias that already exists
        // is a warning - turned into a 500 on whatever page was being served.
        // an application's own class, not loaded yet, is never replaced (declareAlias)
        $this->declareAliases();

        // One builder at a time. Without this, every request that finds the
        // pool missing (a cache:clear just swapped the cache dir, a fresh
        // deploy) scans the bundles concurrently and each rewrites the pool;
        // the rewrite itself is atomic (tempnam + rename), but the requests
        // in between booted from whatever they had read. Waiting on the lock
        // and re-reading the pool means only the first request pays for the
        // scan and the others start from its result.
        $lock = null;
        if ($needsWarmup) {
            $lock = $this->acquirePoolLock();
            if ($lock && $this->reloadPool()) {
                $needsWarmup = false;
            }
        }

        if ($needsWarmup) {

            foreach(array_reverse($this->getBundles()) as $baseBundle)
            {
                if($baseBundle == BaseBundle::class) continue;

                $classRefl = new \ReflectionClass($baseBundle);
                $classPath = dirname($classRefl->getFileName());

                $baseNamespace = dirname_namespace($baseBundle);
                foreach(self::getDirectories($classPath, 1) as $namepath)
                {
                    $namespace = basename($namepath);

                    $baseClass = $baseNamespace . "\\". $namespace;
                    $baseClassArray = explode("\\", $baseClass);
                    array_swap($baseClassArray, 1, 2);
                    
                    $baseClassSwap = implode("\\", $baseClassArray);
                    $this->setMapping($classPath . "/".$namespace     , $baseClass     , $baseClassSwap);
                }
            }

            $this->setMapping($this->getBundleDir() . "/src/Entity"    , "Base\Entity"    , "App\Entity");
            $this->setMapping($this->getBundleDir() . "/src/Repository", "Base\Repository", "App\Repository");
            // The core's repositories, in omnibridge/doctrine (plan "omnibase agnostic", F3), by their former names.
            if (null !== $repositories = self::doctrineRepositoryDir()) {
                $this->setMapping($repositories, "Base\Repository", "App\Repository", "Omnibridge\Doctrine\Omnibase\Repository");
            }
            $this->setMapping($this->getBundleDir() . "/src/Enum"      , "Base\Enum"      , "App\Enum");

            $this->setMapping($this->getBundleDir() . "/src/Tests"     , "Base\Tests"     , "App\Tests");
            $this->setMapping($this->getBundleDir() . "/src/Enum"      , "Base\Enum"      , "App\Enum");
            $this->setMapping($this->getBundleDir() . "/src/Notifier"  , "Base\Notifier"  , "App\Notifier");
            $this->setMapping($this->getBundleDir() . "/src/Form"      , "Base\Form"      , "App\Form");
            // The core's form types, extensions and services, in omnibridge/symfony (plan "omnibase agnostic", F5b), by their former names.
            if (null !== $forms = self::symfonyFormDir()) {
                $this->setMapping($forms, "Base\Form", "App\Form", "Omnibridge\Symfony\Omnibase\Form");
            }
            $this->setMapping($this->getBundleDir() . "/src/Entity"    , "Base\Entity"    , "App\Entity");

            self::getAllClasses($this->getBundleDir() . "/src/Database/Attribute");
            self::getAllClasses($this->getBundleDir() . "/src/Attributes/Attribute");
            
            self::getAllClasses($this->getBundleDir() . "/src/Enum");
            self::getAllClasses($this->getProjectDir() . "/src/Enum");
            
            // The class each alias stands for where it is not the name listed (a former name, an alias its
            // file declares): what declares the alias once loaded (deferAlias()).
            self::$aliasDeclared = [];
            foreach ((self::$aliasList ?? []) + (self::$aliasRepositoryList ?? []) as $class => $alias) {
                if ($class !== $declared = self::declaredName($class)) {
                    self::$aliasDeclared[$class] = $declared;
                }
            }

            self::$cache->warmUp([
                "base.files" => self::$files ?? [],
                "base.classes" => self::$classes ?? [],
                "base.alias_list" => self::$aliasList ?? [],
                "base.alias_repository_list" => self::$aliasRepositoryList ?? [],
                "base.alias_declared" => self::$aliasDeclared,
            ]);
        }

        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * The directory of the core's repositories: omnibridge/doctrine's Omnibase\Repository since
     * the plan "omnibase agnostic"'s step F3, null where omnibridge/doctrine is not installed.
     */
    public static function doctrineRepositoryDir(): ?string
    {
        $class = 'Omnibridge\Doctrine\Omnibase\Repository\UserRepository';

        return class_exists($class) ? dirname((new \ReflectionClass($class))->getFileName()) : null;
    }

    /**
     * The directory of the core's form types, extensions and services: omnibridge/symfony's
     * Omnibase\Form since the plan "omnibase agnostic"'s step F5b, null where it is not installed.
     */
    public static function symfonyFormDir(): ?string
    {
        $class = 'Omnibridge\Symfony\Omnibase\Form\FormProxy';

        return class_exists($class) ? dirname((new \ReflectionClass($class))->getFileName()) : null;
    }

    /**
     * The class each alias of the pool stands for, where it is not the name the pool lists:
     * written with the pool, null for a pool written before it (whose aliases are declared at once).
     *
     * @var array<string, string>|null
     */
    protected static ?array $aliasDeclared = null;

    /**
     * The pool's aliases. With debug, each one only once no autoloader finds
     * a file of the application for it (a class added since the pool was
     * written wins at once). Without debug the pool is the code's - written by
     * the scan of the cache:clear that came with it, for names no class
     * answered to - and that search, a file_exists() missed per alias, is
     * left out of the request: 8.7 ms of a page of lesringostars.
     *
     * Each one is declared when its class is loaded or the alias asked
     * (deferAlias()), not here: declaring them all loaded every class of every
     * bundle, 150 to 300 ms of each command and request.
     */
    private function declareAliases(): void
    {
        $lookup = !$this->container->hasParameter('kernel.debug') || (bool) $this->container->getParameter('kernel.debug');
        foreach ([self::$aliasList ?? [], self::$aliasRepositoryList ?? []] as $aliases) {
            foreach ($aliases as $class => $alias) {
                if (null === self::$aliasDeclared) {
                    self::declareAlias($class, $alias, $lookup);
                } else {
                    self::deferAlias($class, $alias, self::$aliasDeclared[$class] ?? $class, $lookup);
                }
            }
        }
    }

    /**
     * Blocks until this process holds the pool lock, or returns null when the
     * lock cannot be taken (read-only cache dir): the caller then scans on its
     * own, which is the pre-lock behaviour, never a failure.
     *
     * @return resource|null
     */
    private function acquirePoolLock()
    {
        $dir = $this->getCacheDir() . "/pools/base";
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return null;
        }
        $fp = @fopen($dir . "/bundle.lock", "c");
        if (!$fp) {
            return null;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    /**
     * After waiting on the lock, another request may have built the pool:
     * re-read it (a fresh adapter, the previous one memoised the miss) and
     * register its aliases. True when the pool is usable.
     */
    private function reloadPool(): bool
    {
        $file = $this->getCacheDir() . "/pools/base/bundle.php";
        if (!file_exists($file)) {
            return false;
        }
        $cache = new PhpArrayAdapter($file, new FilesystemAdapter("", 0, $this->getCacheDir() . "/pools/base/fallback"));
        $aliasList = $cache->getItem('base.alias_list')->get() ?? [];
        $aliasRepositoryList = $cache->getItem('base.alias_repository_list')->get() ?? [];
        if (empty($aliasList) && empty($aliasRepositoryList)) {
            return false;
        }

        self::$cache = $cache;
        self::$files = $cache->getItem('base.files')->get() ?? [];
        self::$classes = $cache->getItem('base.classes')->get() ?? [];
        self::$aliasList = $aliasList;
        self::$aliasRepositoryList = $aliasRepositoryList;
        self::$aliasDeclared = $cache->getItem('base.alias_declared')->get();
        // an application's own class, not loaded yet, is never replaced (declareAlias)
        $this->declareAliases();
        return true;
    }

    public function boot(): void
    {
        if (!extension_loaded('imagick')) {
           throw new EnvNotFoundException('Application requires `imagick`, but it is not enabled.');
        }

        if (!extension_loaded('igbinary')) {
           throw new EnvNotFoundException('Application requires `igbinary`, but it is not enabled.');
        }

        // FIRST, before any service is constructed: registering the
        // App\* => Base\* class aliases is what makes an application entity
        // loadable at all (App\Entity\Article\Tag extends
        // App\Entity\Thread\Tag, which only exists as an alias). Anything
        // below that reaches Doctrine metadata - the AttributeReader graph
        // does - loads those entity files, and with no aliases yet that is a
        // fatal "Attempted to load class Tag from namespace
        // App\Entity\Thread". It stayed hidden for as long as the metadata
        // pool was warm, since a cache hit never loads the classes; deleting
        // var/cache/<env>/pools left the application unbootable, console
        // included, with no way to warm it back up.
        if (!self::$cache) {
            $this->warmUp();
        }

        // Seed the lazy runtime for BaseTrait/BaseCommonTrait static accessors.
        // boot() runs for HTTP, console AND bare kernel boots, so the statics
        // work everywhere without eagerly constructing BaseService's full
        // dependency graph — the locator only holds closures; each service is
        // instantiated on its first actual accessor call.
        if ($this->container->has('base.runtime')) {
            Locator::setRuntime($this->container->get('base.runtime'));
        }
        // The contracts of the Foundation (Base\Contract\Runtime), from the
        // locator the core's `contracts` registry names (Base\Discovery, built by
        // DiscoveryPass): closures as well, nothing constructed
        // before a contract is asked; a new kernel's locator replaces the
        // previous one's, and what was resolved from it is forgotten.
        $contracts = class_exists(\Base\Discovery::class) ? \Base\Discovery::registry('contracts')['service'] : ContractsPass::SERVICE;
        if ($this->container->has($contracts)) {
            Runtime::setContainer($this->container->get($contracts));
        }
        Locator::setProjectDir($this->container->getParameter('kernel.project_dir'));
        Locator::setEnvironment($this->container->getParameter('kernel.environment'));

        // The demonstration refuses to start in debug or on the production
        // database (docs/20-architecture/demo.md): asked here, for a page as
        // for a command, before anything is read or written.
        if (DemoMode::ENVIRONMENT === $this->container->getParameter('kernel.environment') && $this->container->has(DemoGuard::class)) {
            $this->container->get(DemoGuard::class)->check();
        }

        // Doctrine constructs SQLFilter classes (e.g. TrashFilter) itself, bypassing
        // the DI container entirely, so they can only reach AttributeReader through
        // its getInstance() singleton. A core whose list of attributes the container
        // holds (AttributeReader::NAMES, AttributesPass) makes the container's reader
        // when it is first asked, through the contracts' registry handed above: it is
        // not made here, with the router, the entity manager and the rest it is given,
        // for a command or a request that reads no attribute. A previous kernel's
        // reader is forgotten, so that this container's is the one asked. An older
        // core is given its reader here, as before.
        if ($this->container->has(AttributeReader::class)) {
            if (!\defined(AttributeReader::class.'::NAMES')) {
                $this->container->get(AttributeReader::class);
            } elseif (!$this->container->initialized(AttributeReader::class)) {
                AttributeReader::setInstance(null);
            }
        }
        // The router's matcher and generator are omnibase's from the container's compilation (AdvancedRouterPass sets
        // Symfony's router's matcher_class and generator_class): they make omnibase's router from this container when
        // they first need it, which is no longer made here for a command or a request that routes nothing.
        if ($this->container->has('advanced_router')) {
            AdvancedRouter::setContainer($this->container);
        }

        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {

            $htmlDumper = new HtmlDumper();
            $cliDumper = new CliDumper();

            \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var) use ($htmlDumper, $cliDumper) {

                static $startTime = null;
                if ($startTime === null) {
                    $startTime = microtime(true);
                }

                if(is_cli()) {
                    $dumper = $cliDumper;
                } else {
                    $dumper = $htmlDumper;
                }

                $dumper->dump(
                    (new \Symfony\Component\VarDumper\Cloner\VarCloner())->cloneVar($var)
                );
            });
        }

        
        if ($this->container->getParameter("base.database.use_custom")) {
                
            // Start session here to access client information
            $timezone = null;
            $timezone = User::getCookie("timezone");
            if (!in_array($timezone, timezone_identifiers_list())) $timezone = "UTC";

            // Set default time to UTC everywhere
            date_default_timezone_set($timezone);
            // Instants are stored in UTC. A date is a calendar day with no time
            // and no zone: Doctrine's own "date" type (DATE, Y-m-d). It was
            // overridden with the UTC datetime too, which made a "date" field a
            // DATETIME holding the visitor's midnight in UTC (15:00 the day
            // before, for a visitor in Tokyo), and a real DATE column never
            // compared equal to its mapping.
            //
            // What Omnibridge\Doctrine\Types::register() does, with omnibase's
            // names: DateTimeTypeUTC is omnibridge/doctrine's DateTimeType, and
            // utc_datetime(_immutable) are registered with the connection
            // (BaseExtension::prependDoctrineTypes()). register() itself is not
            // called: it would put omnibridge's classes under these names, so a
            // type asked of Doctrine would no longer be the Base\Database\Type
            // class applications and bundles name, and it ignores use_custom
            // (tests/Database/Type/OmnibridgeTypesTest says both convert alike).
            //
            // The column types and the filters are Base\Bridge\Doctrine's (plan "omnibase agnostique", C6),
            // what an entity manager built by hand is given as well: datetime and datetimetz the core's
            // DateTimeTypeUTC, array its ArrayType (backward compatibility, see doctrine:array:upgrade),
            // the UTC types, every enumeration of the core's src/Enum and of the application's.
            // Where Doctrine is there (plan "omnibase agnostic", step B3): omnibridge/eloquent casts the same types itself.
            // The enumerations of both folders were found when the container was compiled (EnumerationsPass): the
            // boot registers that list, it no longer reads the folders and loads each of their classes.
            if (class_exists(Types::class) && $this->container->has('doctrine.orm.entity_manager')) {
                if ($this->container->hasParameter(EnumerationsPass::PARAMETER)) {
                    Types::register();
                    Types::registerEnumerations($this->container->getParameter(EnumerationsPass::PARAMETER));
                } else {
                    Types::register([], EnumerationsPass::directories($this->getProjectDir()));
                }
                Configuration::enableFilters($this->container->get('doctrine.orm.entity_manager'), $this->getEnvironment());
            }
        }

        CacheClearCommand::$testFile ??= $this->getCacheDir().".txt";
        $this->boot = true;
    }

    /**
     * A kernel that shuts down handles no request any more. get_url() reads
     * the main request first, through BaseService's static stack, which
     * outlives the kernel: a request left on it (a test that pushed one and
     * never popped it) was the current address of every call made after the
     * kernel was gone, instead of PHP's globals.
     */
    public function shutdown(): void
    {
        if ($this->container?->initialized('request_stack')) {
            $requests = $this->container->get('request_stack');
            while ($requests->pop()) {
            }
        }
        // omnibridge/eloquent's connection let go of the PDO it shares with Doctrine (base.persistence: eloquent, step
        // F7d-3), as DoctrineBundle closes its own connections: a test suite booting kernel after kernel kept one
        // database connection open per kernel otherwise (MySQL's "Too many connections").
        if ($this->container?->initialized('omnibridge.eloquent.connection')) {
            $this->container->get('omnibridge.eloquent.connection')->disconnect();
        }
        if ($this->container) {
            AdvancedRouter::forgetContainer($this->container);
        }

        parent::shutdown();
    }

    public function isBuilt() { return $this->container !== null; }
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
	    $this->container = $container;

        // omniauth's sign-in (config/packages/omniauth.yaml; plan "omniauth", AU7), where omniauth and
        // an omnibridge/symfony that bridges it are installed: an omnibase site registers no OmnibridgeBundle.
        if (class_exists(\Omnibridge\Symfony\Auth\DependencyInjection\OmniauthExtension::class)) {
            \Omnibridge\Symfony\Auth\DependencyInjection\OmniauthExtension::register($container);
        }
        // omnimap's maps and their cache (config/packages/omnimap.yaml), where omnimap and an
        // omnibridge/symfony that bridges it are installed - for the same reason.
        if (class_exists(\Omnibridge\Symfony\Map\DependencyInjection\OmnimapExtension::class)) {
            \Omnibridge\Symfony\Map\DependencyInjection\OmnimapExtension::register($container);
        }

        if (!self::$cache) {
            $this->warmUp();
        }

        /* Register compiler passes */
        // What the core declares is discovered among its services (Base\Discovery, plan "omnibase agnostic", step F5e
        // slice 4c) is compiled by one generic pass; the passes left in Compiler/Pass are Symfony's own (TwigComponent,
        // DoctrineConfiguration, WebauthnMapping, WebauthnDeprecatedAlias, Workflow, FormerFormTypes). A core without
        // the declaration (4.x) has the former passes, which held its lists (Legacy\).
        $directories = [__DIR__ . '/DependencyInjection/Compiler/Pass' => __NAMESPACE__ . '\\DependencyInjection\\Compiler\\Pass\\'];
        if (class_exists(\Base\Discovery::class)) {
            DiscoveryPass::register($container, \Base\Discovery::registries());
        } else {
            $directories[__DIR__ . '/Legacy/DependencyInjection/Compiler/Pass'] = __NAMESPACE__ . '\\Legacy\\DependencyInjection\\Compiler\\Pass\\';
        }
        foreach ($directories as $directory => $namespace) {
            $finder = new \Symfony\Component\Finder\Finder();
            $finder->files()
                ->in($directory)
                ->name('*Pass.php')
                ->notName('AbstractPass.php');

            foreach ($finder as $file) {
                $class = $namespace . $file->getBasename('.php');
                if (class_exists($class) && !in_array($class, [__CLASS__])) {
                    $priority = method_exists($class, 'getPriority') ? $class::getPriority() : 0;
                    $container->addCompilerPass(new $class(), priority: $priority);
                }
            }
        }

        /* Register aliased repositories */
        foreach (self::$aliasRepositoryList as $baseRepository => $aliasedRepository) {

            // A pool written before abstract repositories were set aside may
            // still list one: never registered (the container cannot build it).
            if (!self::isRepositoryService($baseRepository)) {
                continue;
            }

            $container->register($baseRepository)
                      ->addTag("doctrine.repository_service")
                      ->addArgument(new Reference('doctrine'));

            if ($aliasedRepository) {
                $container->register($aliasedRepository)
                          ->addTag("doctrine.repository_service")
                          ->addArgument(new Reference('doctrine'));
            }
        }
    }
}
