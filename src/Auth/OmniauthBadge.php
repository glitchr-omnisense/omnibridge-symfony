<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\Result;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * On a passport OmniauthAuthenticator made: omniauth decided this sign-in
 * (Result), and its events have already been heard - an application's
 * listener of Security's own events (CheckPassportEvent, LoginSuccessEvent,
 * LoginFailureEvent) that does what an omniauth listener does skips a
 * passport that carries it.
 */
final class OmniauthBadge implements BadgeInterface
{
    public function __construct(public readonly Result $result)
    {
    }

    public function isResolved(): bool
    {
        return true;
    }
}
