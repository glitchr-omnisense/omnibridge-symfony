<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\Contract\State;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * omniauth's State under Symfony: the visitor's session (the request's,
 * through the request stack). Without a session - a command, a request
 * without one - nothing is kept.
 */
final class SessionState implements State
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function get(string $key): mixed
    {
        $request = $this->requests->getCurrentRequest();

        return null !== $request && $request->hasSession() ? $request->getSession()->get($key) : null;
    }

    public function set(string $key, mixed $value): void
    {
        $request = $this->requests->getCurrentRequest();
        if (null !== $request && $request->hasSession()) {
            $request->getSession()->set($key, $value);
        }
    }

    public function remove(string $key): void
    {
        $request = $this->requests->getCurrentRequest();
        if (null !== $request && $request->hasSession()) {
            $request->getSession()->remove($key);
        }
    }
}
