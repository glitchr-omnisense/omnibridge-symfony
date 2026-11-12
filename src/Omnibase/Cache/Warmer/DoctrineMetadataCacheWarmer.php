<?php

namespace Omnibridge\Symfony\Omnibase\Cache\Warmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 * DoctrineBundle's metadata cache warmer (doctrine.orm.<name>_metadata_cache_warmer, outside debug: every entity's
 * metadata in a PHP file), which DoctrineConfigurationPass puts in its place, with its arguments. DoctrineBundle's
 * refuses to warm once its entity manager has loaded any metadata ("DoctrineMetadataCacheWarmer must load metadata
 * first"), as that factory would leave what it loaded out of the file. Under omnibase the metadata is always loaded by
 * then: the settings laid over the parameters at each command's start (HotParameterBagSubscriber reads them through
 * the entity manager), the reader's and the manipulator's own warmers when the container is built; `cache:warmup` and
 * `cache:clear` failed in every environment without debug. Where nothing is loaded, this does what DoctrineBundle's
 * does; where something is, the file is written by a factory of its own over the same entity manager, which loads
 * every class itself - the file holds what DoctrineBundle's would have written in a process that loaded nothing first.
 */
final class DoctrineMetadataCacheWarmer extends AbstractPhpFileCacheWarmer
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly string $phpArrayFile)
    {
        parent::__construct($phpArrayFile);
    }

    public function isOptional(): bool
    {
        return true;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        // cache already warmed up, no needs to do it again
        if (is_file($this->phpArrayFile)) {
            return false;
        }

        $metadataFactory = $this->entityManager->getMetadataFactory();
        if ($metadataFactory->getLoadedMetadata()) {
            /** @var class-string<ClassMetadataFactory> $class */
            $class = $this->entityManager->getConfiguration()->getClassMetadataFactoryName();
            $metadataFactory = new $class();
            $metadataFactory->setEntityManager($this->entityManager);
        }

        $metadataFactory->setCache($arrayAdapter);
        $metadataFactory->getAllMetadata();

        return true;
    }

    /**
     * As DoctrineBundle's: no null written.
     *
     * @param array<string, mixed> $values
     *
     * @return string[]
     */
    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values): array
    {
        return parent::warmUpPhpArrayAdapter($phpArrayAdapter, array_filter($values, static fn ($value): bool => null !== $value));
    }
}
