<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Environment;

/**
 * Omnibridge\Contract\Environment under Symfony: the kernel's environment,
 * debug and secret - the container's kernel.environment, kernel.debug and
 * kernel.secret.
 *
 * Moved from omnisense/omnibase's Base\Bridge\Symfony\KernelEnvironment
 * (plan "omnibase agnostique", A1); the former name is an alias of this class.
 */
final class KernelEnvironment implements Environment
{
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug,
        #[\SensitiveParameter] private readonly string $secret,
    ) {
    }

    public function name(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function secret(): string
    {
        return $this->secret;
    }
}
