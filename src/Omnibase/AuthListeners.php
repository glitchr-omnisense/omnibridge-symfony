<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Auth\Listener\GuardSignIn;
use Base\Auth\Listener\WarnNewDevice;
use Base\Repository\User\Connections;
use Base\Security\SignInGuard;
use Base\Security\UserTracker;
use Base\Service\SecurityPolicy;
use Omniauth\Contract\State;
use Base\Service\FormGuard;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base\Auth\'s listeners of omniauth's events, given what they read of a
 * Symfony request (plan "omniauth", step AU7): the captcha's answer the
 * sign-in form posted (GuardSignIn - at the top of the request, where the
 * widget writes it, or under the form, as SignInGuard read it), and whether
 * this browser declined the offer of a second factor (WarnNewDevice).
 */
final class AuthListeners
{
    public static function guardSignIn(SignInGuard $guard, RequestStack $requests, array $firewalls = ['main'], string $form = '_base_security_login'): GuardSignIn
    {
        return new GuardSignIn($guard, static function (string $field) use ($requests, $form): ?string {
            $request = $requests->getCurrentRequest();
            if (null === $request) {
                return null;
            }
            $fields = $request->request->all($form);
            $answer = $request->request->get($field) ?? ($fields[FormGuard::CHALLENGE_FIELD] ?? null);

            return \is_string($answer) ? $answer : null;
        }, $firewalls);
    }

    public static function warnNewDevice(SecurityPolicy $policy, UserTracker $tracker, Connections $connections, RequestStack $requests, ?State $state = null): WarnNewDevice
    {
        return new WarnNewDevice($policy, $tracker, $connections, $state, static fn (): bool => (bool) $requests->getCurrentRequest()?->cookies->has(SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED));
    }
}
