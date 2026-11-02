<?php

namespace Omnibridge\Symfony\Omnibase\Cache\Warmer;

use Base\Attributes\AttributeReader;
use Base\Cache\Abstract\AbstractLocalCache;
use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Omnibridge\Symfony\Omnibase\Cache\Abstract\AbstractLocalCacheWarmer;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class MetadataCacheWarmer extends AbstractLocalCacheWarmer
{
    protected AttributeReader $attributeReader;

    public function __construct(AbstractLocalCacheInterface $simpleCache, AttributeReader $attributeReader, string $cacheDir)
    {
        parent::__construct($simpleCache, $cacheDir);
        $this->attributeReader = $attributeReader;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!parent::doWarmUp($cacheDir, $arrayAdapter, $buildDir)) {
            return false;
        }

        if ($this->simpleCache instanceof ClassMetadataManipulator) {
            $this->simpleCache->enrichAndSaveCompletors($this->attributeReader);
        }

        return true;
    }
}
