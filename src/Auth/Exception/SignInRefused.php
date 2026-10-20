<?php

namespace Omnibridge\Symfony\Auth\Exception;

use Omniauth\Factor;
use Omniauth\Failure;
use Omniauth\PendingIdentity;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * An attempt omniauth (or the bridge, for a CSRF token) refused: the factor,
 * omniauth's stable failure and reason, and the message the page translates
 * (OmniauthAuthenticator's `messages`). IdentityUnknown is one too: a provider
 * vouched for someone no account is linked to ($identity).
 */
final class SignInRefused extends CustomUserMessageAuthenticationException
{
    public function __construct(
        public readonly Factor $factor,
        public readonly ?Failure $failure,
        string $message,
        array $messageData = [],
        public readonly ?string $reason = null,
        public readonly ?PendingIdentity $identity = null,
    ) {
        parent::__construct($message, $messageData);
    }
}
