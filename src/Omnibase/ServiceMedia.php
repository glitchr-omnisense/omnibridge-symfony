<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Contract\Media;
use Base\Service\MediaService;

/**
 * The Media contract under Symfony (plan "omnibase agnostique", C4b):
 * omnibase's MediaService - its extension of a file, its obfuscation of the
 * image route's data, its address of that route - the answers an entity got
 * through BaseTrait's getMediaService(), the same strings.
 */
final class ServiceMedia implements Media
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function extension(?string $path): ?string
    {
        return $this->media->getExtension($path);
    }

    public function obfuscate(?string $path, array $config = [], array $filters = []): ?string
    {
        return $this->media->obfuscate($path, $config, $filters);
    }

    public function link(string $route, array $parameters, ?string $path, array $config = []): ?string
    {
        return $this->media->generate($route, $parameters, $path, $config);
    }
}
