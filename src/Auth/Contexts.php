<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\Context;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * omniauth's Context of a Symfony request: its origin and host (WebAuthn's),
 * the visitor's address and browser, the trusted_device cookie, and the
 * firewall - the "fwl" claim of the trusted devices' tokens, the firewall of
 * the request unless one is set ($firewall: scheb/2fa's tokens named the
 * firewall that asked the second factor).
 */
final class Contexts
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly ?FirewallMap $firewalls = null,
        private readonly string $cookie = 'trusted_device',
        private readonly ?string $firewall = null,
    ) {
    }

    public function of(?Request $request = null): Context
    {
        $request ??= $this->requests->getCurrentRequest() ?? throw new \LogicException('No request to sign in from.');
        $cookie = $request->cookies->get($this->cookie);

        return new Context(
            origin: $request->getSchemeAndHttpHost(),
            host: $request->getHost(),
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            trustedDevice: \is_string($cookie) && '' !== $cookie ? $cookie : null,
            firewall: $this->firewall ?? $this->firewallName($request) ?? 'main',
        );
    }

    /** The name of the firewall the request is in, if any. */
    public function firewallName(Request $request): ?string
    {
        return $this->firewalls?->getFirewallConfig($request)?->getName();
    }
}
