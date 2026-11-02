<?php

namespace Omnibridge\Symfony\Omnibase;

use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;

/**
 * Base\Contract\PasskeyRecords under Symfony (plan "omniauth", AU1):
 * WebauthnBundle's CredentialRecordRepositoryInterface and
 * CanSaveCredentialRecord, which Base\Repository\User\PasskeyRepository
 * implemented itself up to AU1 and which the contract is an alias of when the
 * bundle is installed. Not to be named by a class: implement
 * Base\Contract\PasskeyRecords.
 */
interface PasskeyRecords extends CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
}
