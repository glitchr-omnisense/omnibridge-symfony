<?php

namespace Omnibridge\Symfony\Omnibase;

use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;

/**
 * Base\Contract\TwoFactorUser with scheb/2fa (plan "omnibase agnostique", C7b):
 * the interfaces Base\Entity\User implemented itself up to 4.0 - TOTP and
 * e-mail codes, backup codes, trusted devices -, which the contract is an
 * alias of when scheb/2fa is installed. Not to be named by a class: implement
 * Base\Contract\TwoFactorUser.
 */
interface TwoFactorUser extends TotpTwoFactorInterface, EmailTwoFactorInterface, BackupCodeInterface, TrustedDeviceInterface
{
}
