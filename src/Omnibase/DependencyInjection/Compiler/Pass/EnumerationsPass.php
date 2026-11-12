<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Doctrine\Omnibase\Types;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The enumerations BaseBundle::boot() registers with DBAL (omnibridge/doctrine's Types: those of the core's src/Enum
 * and of the application's, under the names not taken), found once when the container is compiled, in the parameter
 * PARAMETER. Types::register() read both folders and loaded each of their classes at every kernel's boot; the boot
 * registers the list now (Types::registerEnumerations()). Each folder is a resource of the container: in debug, a file
 * added, changed or removed there rebuilds the container, and the list with it (a folder that does not exist yet as
 * well, once made).
 */
final class EnumerationsPass implements CompilerPassInterface
{
    public const PARAMETER = 'omnibridge.doctrine.enumerations';

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Types::class)) {
            return;
        }

        $directories = self::directories((string) $container->getParameter('kernel.project_dir'));
        foreach ($directories as $directory) {
            $container->fileExists($directory, '/\.php$/');
        }

        $container->setParameter(self::PARAMETER, Types::enumerations($directories));
    }

    /** @return list<string> the folders whose enumerations the boot registers: the core's, then the application's */
    public static function directories(string $projectDir): array
    {
        return [Types::coreEnumerationDirectory(), $projectDir.'/src/Enum'];
    }
}
