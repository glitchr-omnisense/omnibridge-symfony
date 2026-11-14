<?php

namespace Omnibridge\Symfony\Omnibase\Cache\Abstract;

use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Cache\Abstract\AbstractLocalCacheWarmerInterface;
use Omnibridge\Symfony\Omnibase\BaseBundle;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class AbstractLocalCacheWarmer extends AbstractPhpFileCacheWarmer implements AbstractLocalCacheWarmerInterface
{
    /** @var string|null */
    private ?string $cacheFile;

    protected int $shellVerbosity = 0;

    /** @var ?AbstractLocalCacheInterface */
    protected ?AbstractLocalCacheInterface $simpleCache;

    protected ArrayAdapter $arrayAdapter;
    public function __construct(AbstractLocalCacheInterface $simpleCache, string $cacheDir)
    {
        $this->shellVerbosity = getenv('SHELL_VERBOSITY');

        $this->simpleCache = $simpleCache;
        $this->cacheFile = $cacheDir . '/pools/simple/php/' . str_replace(['\\', '/'], ['__', '_'], get_class($simpleCache)) . '.php';

        parent::__construct($this->cacheFile);
    }

    public function getCache(): ArrayAdapter
    {
        return $this->arrayAdapter;
    }

    public function isOptional(): bool
    {
        return false;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!$this->cacheFile) {
            return false;
        }

        if (is_file($this->cacheFile)) {
            return false;
        }

        if ($this->shellVerbosity > 0 && 'cli' == php_sapi_name()) {
            echo ' // Warming up cache... ' . ucwords(camel2snake(str_replace('CacheWarmer', '', class_name(static::class)), ' ')) . PHP_EOL . PHP_EOL;
        }

        $this->simpleCache?->setCache($arrayAdapter);
        $this->simpleCache?->warmUp($cacheDir, $buildDir);

        return true;
    }
}
