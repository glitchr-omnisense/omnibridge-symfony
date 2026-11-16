<?php

namespace Base\Cache\Warmer;

use Base\Attributes\AttributeReader;
use Base\Cache\Abstract\AbstractLocalCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class AttributeCacheWarmer extends AbstractLocalCacheWarmer
{
    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!parent::doWarmUp($cacheDir, $arrayAdapter, $buildDir)) {
            return false;
        }

        // Heavy attribute precompute (every entity class + every routed
        // controller) belongs HERE, at cache:warmup time — not in
        // AttributeReader's constructor, where the route-collection load
        // could re-enter a Doctrine event dispatch (ClassMetadata race).
        // parent::doWarmUp() already pointed the reader's cache at
        // $arrayAdapter, so the precomputed buckets end up in the dumped
        // PHP-array cache file.
        if ($this->simpleCache instanceof AttributeReader) {
            $this->simpleCache->precompute();
        }

        return true;
    }
}
