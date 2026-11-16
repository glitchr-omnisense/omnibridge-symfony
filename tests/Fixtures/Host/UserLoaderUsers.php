<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Base\Contract\SecurityUserLoader;
use Base\Repository\Users;

/**
 * The users' repository as UserProvider takes it: omnibase's Users and the security loader (SignInConnectionTest).
 * In a file of its own, autoloaded once the test runs: without omnisense/omnibase, the test is skipped before.
 */
interface UserLoaderUsers extends Users, SecurityUserLoader
{
}
