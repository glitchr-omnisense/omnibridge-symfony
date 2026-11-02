<?php

namespace Omnibridge\Symfony\Omnibase;

use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Base\Contract\SecurityUserProvider under Symfony (audit "lesringostars face
 * au master plan", R2): Security's UserProviderInterface and
 * PasswordUpgraderInterface, which Base\Repository\UserRepository implemented
 * itself up to 4.0 and which the contract is an alias of when
 * symfony/security-core is installed. Not to be named by a class: implement
 * Base\Contract\SecurityUserProvider.
 */
interface SecurityUserProvider extends UserProviderInterface, PasswordUpgraderInterface
{
}
