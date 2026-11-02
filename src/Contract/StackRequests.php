<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Requests;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Omnibridge\Contract\Requests under Symfony: the request stack's current
 * request, and the main request's session (a sub-request has none of its
 * own) with its flash bag.
 */
final class StackRequests implements Requests
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function has(): bool
    {
        return null !== $this->requestStack->getCurrentRequest();
    }

    public function ip(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }

    public function header(string $name): ?string
    {
        return $this->requestStack->getCurrentRequest()?->headers->get($name);
    }

    public function uri(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getUri();
    }

    public function host(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getSchemeAndHttpHost();
    }

    public function route(): ?string
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        return \is_string($route) ? $route : null;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->requestStack->getCurrentRequest()?->attributes->get($name, $default) ?? $default;
    }

    public function session(string $key, mixed $default = null): mixed
    {
        return $this->sessionOf()?->get($key, $default) ?? $default;
    }

    public function remember(string $key, mixed $value): void
    {
        $session = $this->sessionOf();
        if (null === $session) {
            return;
        }
        null === $value ? $session->remove($key) : $session->set($key, $value);
    }

    public function forget(string $key): void
    {
        $this->sessionOf()?->remove($key);
    }

    public function flash(string $type, string $message): void
    {
        $session = $this->sessionOf();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    public function flashes(): array
    {
        $session = $this->sessionOf();

        return $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->peekAll() : [];
    }

    public function request(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    private function sessionOf(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return null !== $request && $request->hasSession() ? $request->getSession() : null;
    }
}
