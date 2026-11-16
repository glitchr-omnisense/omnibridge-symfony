<?php

namespace Base\Cache\Warmer;

use PhpOffice\PhpSpreadsheet\Settings;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class SpreadsheetCacheWarmer implements CacheWarmerInterface
{
    protected int $shellVerbosity;

    protected string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->shellVerbosity = getenv('SHELL_VERBOSITY');
        $this->cacheDir = $cacheDir;
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->shellVerbosity > 0 && 'cli' == php_sapi_name()) {
            echo ' // Warming up cache... PHP Spreadsheet' . PHP_EOL . PHP_EOL;
        }

        // Implement phpspreadsheet cache
        $psr6Cache = new FilesystemAdapter('phpspreadsheet', 0, $this->cacheDir);
        $psr16Cache = new Psr16Cache($psr6Cache);
        Settings::setCache($psr16Cache);

        return [get_class($psr16Cache)];
    }
}
