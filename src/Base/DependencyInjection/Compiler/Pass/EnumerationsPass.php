<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Omnibridge\Doctrine\Omnibase\Types;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The enumerations of the connections' type provider (omnibridge/doctrine's Types: those of the core's src/Enum, of
 * the application's and, since 3.2, of each registered omnibase bundle's, under the names not taken), found once when the container is compiled, in the parameter
 * PARAMETER. Types::register() read both folders and loaded each of their classes at every kernel's boot; the list is
 * given to Types::provider() now, the connections' type provider DiscoveryPass registers (omnibridge/doctrine 4.0).
 * Each folder is a resource of the container: in debug, a file
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

        $directories = self::directories((string) $container->getParameter('kernel.project_dir'), \Base\DependencyInjection\Compiler\DiscoveryPass::packages($container));
        foreach ($directories as $directory) {
            $container->fileExists($directory, '/\.php$/');
        }

        $container->setParameter(self::PARAMETER, Types::enumerations($directories));
    }

    /**
     * The folders whose enumerations the type provider holds: the core's, the application's, then each registered omnibase
     * bundle's src/Enum (3.2: what a package registered as doctrine.dbal.types by hand until then).
     *
     * @param array<string, string> $packages DiscoveryPass::packages(): folder => namespace
     *
     * @return list<string>
     */
    public static function directories(string $projectDir, array $packages = []): array
    {
        $directories = [Types::coreEnumerationDirectory(), $projectDir.'/src/Enum'];
        foreach (array_keys($packages) as $package) {
            if (is_dir($package.'/src/Enum')) {
                $directories[] = $package.'/src/Enum';
            }
        }

        return $directories;
    }
}
