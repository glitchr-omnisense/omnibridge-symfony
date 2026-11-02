<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Service\Storages;
use Base\Service\MediaServiceInterface;
use Omnibridge\Contract\Files;

/**
 * Omnibridge\Contract\Files under Symfony: omnibase's Flysystem service (its storages
 * from flysystem.yaml) and, for a remote storage, MediaService's image route -
 * what Base\Database\Attribute\Uploader resolves a stored file to: a remote
 * storage's file through the /images route first (no local file to link), a
 * local storage's by its path under public/ (Flysystem::getPublic()); its web
 * address is that path less the public directory, as the editor's uploads
 * give it.
 */
final class FlysystemFiles implements Files
{
    public function __construct(
        private readonly Storages $flysystem,
        private readonly ?MediaServiceInterface $media = null,
    ) {
    }

    public function publicPath(string $path, ?string $storage = null): ?string
    {
        if (null !== $this->media && $this->flysystem->isRemote($storage)) {
            $routed = $this->media->image($path, ['storage' => $storage]);
            if (is_string($routed) && '' !== $routed) {
                return $routed;
            }
        }

        $public = $this->flysystem->getPublic($path, $storage);

        return is_string($public) && '' !== $public ? $public : null;
    }

    public function publicUrl(string $path, ?string $storage = null): ?string
    {
        $public = $this->publicPath($path, $storage);
        $publicDir = rtrim((string) $this->flysystem::getPublicDir(), '/');

        return null !== $public && '' !== $publicDir && str_starts_with($public, $publicDir.'/') ? substr($public, \strlen($publicDir)) : $public;
    }

    public function localPath(string $path, ?string $storage = null): ?string
    {
        return $this->flysystem->isRemote($storage) ? null : $this->flysystem->prefixPath($path, $storage);
    }

    public function isRemote(?string $storage = null): bool
    {
        return (bool) $this->flysystem->isRemote($storage);
    }
}
