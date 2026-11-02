<?php

namespace Omnibridge\Symfony\Omnibase;

use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;

/**
 * Base\Contract\PasskeyUsers under Symfony (plan "omniauth", AU1):
 * WebauthnBundle's PublicKeyCredentialUserEntityRepositoryInterface, which
 * Base\Security\WebauthnUserEntityRepository implemented itself up to AU1 and
 * which the contract is an alias of when the bundle is installed. Not to be
 * named by a class: implement Base\Contract\PasskeyUsers.
 */
interface PasskeyUsers extends PublicKeyCredentialUserEntityRepositoryInterface
{
}
