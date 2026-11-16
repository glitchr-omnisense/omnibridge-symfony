<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Each firewall's login_throttling limiter (security.login_throttling.<firewall>.limiter, Symfony's
 * DefaultLoginRateLimiter) behind omnibridge/symfony's LoginThrottle: its refusal's wait said in the window's minutes.
 */
class LoginThrottlingPass implements CompilerPassInterface
{
    private const THROTTLE = 'Omnibridge\Symfony\Omnibase\LoginThrottle';

    public function process(ContainerBuilder $container): void
    {
        if (!interface_exists(\Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface::class)) {
            return;
        }
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!preg_match('/^security\.login_throttling\.[^.]+\.limiter$/', $id) || !is_a((string) $definition->getClass(), \Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface::class, true)) {
                continue;
            }
            $container->register($id.'.said', self::THROTTLE)
                ->setDecoratedService($id)
                ->setArguments([new Reference($id.'.said.inner')]);
        }
    }
}
