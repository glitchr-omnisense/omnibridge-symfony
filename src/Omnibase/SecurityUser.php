<?php

namespace Omnibridge\Symfony\Omnibase;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Base\Contract\SecurityUser under Symfony (plan "omnibase agnostique", C7b):
 * the interfaces Base\Entity\User implemented itself up to 4.0 - Security's
 * UserInterface and PasswordAuthenticatedUserInterface -, which the contract
 * is an alias of when symfony/security-core is installed. Not to be named by
 * a class: implement Base\Contract\SecurityUser.
 */
interface SecurityUser extends UserInterface, PasswordAuthenticatedUserInterface
{
}
