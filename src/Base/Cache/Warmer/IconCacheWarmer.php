<?php

namespace Base\Cache\Warmer;

use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Cache\Abstract\AbstractLocalCacheWarmer;
use Base\Service\IconProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class IconCacheWarmer extends AbstractLocalCacheWarmer
{
    /**
     * @var array[AbstractLocalCacheInterface]
     */
    protected mixed $adapters;

    public function __construct(IconProvider $iconProvider, string $cacheDir)
    {
        $this->adapters = array_filter($iconProvider->getAdapters(), fn($a) => $a instanceof AbstractLocalCacheInterface);
        parent::__construct($iconProvider, $cacheDir);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        $ret = parent::doWarmUp($cacheDir, $arrayAdapter, $buildDir);

        foreach ($this->adapters as $adapter) {
            $adapter->setCache($arrayAdapter);
            $ret &= $adapter->warmUp($cacheDir, $buildDir) === [];
        }

        return $ret;
    }
}
