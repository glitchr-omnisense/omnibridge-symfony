<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * WebauthnExtension (web-auth/webauthn-symfony-bundle 5.3) still aliases the
 * deprecated PublicKeyCredentialSourceRepositoryInterface to the configured
 * credential_repository, next to CredentialRecordRepositoryInterface. Nothing
 * in the bundle or here asks for the deprecated name, but the alias forced
 * PasskeyRepository to keep implementing it (an alias to an interface the
 * class lacks fails `lint:container`), and implementing it raises a
 * deprecation on every boot.
 *
 * Dropping the alias lets the repository implement only the current contract.
 * The id is a string on purpose: `::class` on a deprecated interface is
 * harmless, but a string keeps this pass from ever needing it autoloaded.
 */
class WebauthnDeprecatedAliasPass implements CompilerPassInterface
{
    private const DEPRECATED_ALIAS = 'Webauthn\\Bundle\\Repository\\PublicKeyCredentialSourceRepositoryInterface';

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasAlias(self::DEPRECATED_ALIAS)) {
            $container->removeAlias(self::DEPRECATED_ALIAS);
        }
    }
}
