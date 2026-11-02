<?php

namespace Omnibridge\Symfony\Omnibase;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * Base\Contract\SecurityUserLoader under Symfony (audit "lesringostars face au
 * master plan", R2): doctrine-bridge's UserLoaderInterface - the repository an
 * entity user provider loads a user by its identifier from -, which
 * Base\Repository\UserRepository implemented itself up to 4.0 and which the
 * contract is an alias of when symfony/doctrine-bridge is installed. Apart
 * from Omnibridge\Symfony\Omnibase\SecurityUserProvider: an interface cannot extend
 * both (loadUserByIdentifier() answers ?UserInterface here, UserInterface
 * there), a class implements both. Not to be named by a class: implement
 * Base\Contract\SecurityUserLoader.
 */
interface SecurityUserLoader extends UserLoaderInterface
{
}
