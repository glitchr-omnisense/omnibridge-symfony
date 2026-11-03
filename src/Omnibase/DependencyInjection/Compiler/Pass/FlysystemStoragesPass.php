<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * The storages of Base\Service\Flysystem in a Symfony application without
 * league/flysystem-bundle (plan "omnibase agnostique", C7b). The `flysystem`
 * service reads the services tagged flysystem.storage by their `storage`
 * name, which is what the bundle tags each storage of its configuration
 * with; without the bundle registered, nothing is tagged, and omnibase gives
 * its local storages here, where its demonstration keeps them - the images'
 * cache under var/cache, the uploads and the editor's files under
 * var/storage, each beside its public directory under public/.
 *
 * With the bundle registered nothing is added: the application's
 * config/packages/flysystem.yaml names its storages, as before.
 */
final class FlysystemStoragesPass implements CompilerPassInterface
{
    public const TAG = 'flysystem.storage';

    /** @var array<string, string> each storage's directory */
    public const STORAGES = [
        'local.cache' => '%kernel.project_dir%/var/cache/%kernel.environment%/images',
        'local.cache.public' => '%kernel.project_dir%/public/images',
        'local.uploads' => '%kernel.project_dir%/var/storage/uploads',
        'local.uploads.public' => '%kernel.project_dir%/public/uploads',
        'local.wysiwyg' => '%kernel.project_dir%/var/storage/wysiwyg',
        'local.wysiwyg.public' => '%kernel.project_dir%/public/wysiwyg',
    ];

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasExtension('flysystem')) {
            return;
        }

        $tagged = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $tags) {
            foreach ($tags as $tag) {
                $tagged[$tag['storage'] ?? ''] = true;
            }
        }

        foreach (self::STORAGES as $storage => $directory) {
            if (isset($tagged[$storage]) || $container->has($storage)) {
                continue;
            }

            // As the bundle's local adapter defaults: no lock, links refused, the root created at once.
            $adapter = new Definition(LocalFilesystemAdapter::class, [$directory, null, 0, LocalFilesystemAdapter::DISALLOW_LINKS]);
            $container->setDefinition($storage, (new Definition(Filesystem::class, [$adapter]))->setPublic(false))
                ->addTag(self::TAG, ['storage' => $storage]);
        }
    }
}
