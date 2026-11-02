<?php

namespace Omnibridge\Symfony\Omnibase\Security;

use Base\Security\SignInCaptcha;
use Base\Service\FormGuard;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The sign-in's captcha, after a few failures (Base\Security\SignInCaptcha),
 * under Symfony. Symfony's login_throttling is the first line (five tries a
 * minute, per identifier and address); this one counts the failures from an
 * address - whatever identifiers they tried - and from
 * base.guard.sign_in_after of them on, the sign-in form carries the captcha
 * (SecurityController::Login) and a sign-in without a valid token is refused
 * before its password is even checked. A success forgets the count.
 *
 * Only the site's own sign-in form (security_login): not the rescue door, not
 * the demonstration's one click. Nothing where glitchr/ux-google's reCAPTCHA
 * already guards the sign-in (google.recaptcha.enable), nor without
 * glitchr/omnishield and a captcha.
 */
class SignInGuard extends SignInCaptcha implements EventSubscriberInterface
{
    public function __construct(
        FormGuard $guard,
        ?CacheItemPoolInterface $cache = null,
        bool $google = false,
        ?LoggerInterface $logger = null,
        protected readonly ?RequestStack $requests = null,
    ) {
        parent::__construct($guard, $cache, $google, $logger);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After login_throttling (2080) and before the password is checked (0).
            CheckPassportEvent::class => ['onCheckPassport', 300],
            LoginFailureEvent::class => ['onLoginFailure'],
            LoginSuccessEvent::class => ['onLoginSuccess'],
        ];
    }

    /** The captcha's gateway when a sign-in from this request must pass it; null otherwise. */
    public function required(?Request $request): ?string
    {
        return $this->requiredFor($request?->getClientIp());
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (self::byOmniauth($event->getPassport()) || $event->getException() instanceof \Omnibridge\Symfony\Auth\Exception\SignInRefused) {
            return; // Base\Auth\Listener\GuardSignIn counted it, on omniauth's SignInFailed
        }
        $ip = $event->getRequest()->getClientIp();
        if (null !== $ip) {
            $this->failed($ip);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (self::byOmniauth($event->getPassport())) {
            return;
        }
        $ip = $event->getRequest()->getClientIp();
        if (null !== $ip) {
            $this->forget($ip);
        }
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        if (self::byOmniauth($event->getPassport())) {
            return; // the captcha was asked before omniauth checked the password (GuardSignIn, on Verifying)
        }
        $request = $this->requests?->getCurrentRequest();
        if (null === $request || LoginFormAuthenticator::LOGIN_ROUTE !== $request->attributes->get('_route') || !$request->isMethod('POST') || $event->getAuthenticator() instanceof RescueFormAuthenticator) {
            return;
        }
        if (null === $gateway = $this->required($request)) {
            return;
        }

        $field = $this->field($gateway);
        $form = $request->request->all('_base_security_login') ?: $request->request->all('security_login') ?: [];
        $token = $request->request->get($field) ?? ($form[FormGuard::CHALLENGE_FIELD] ?? '');

        if (!$this->passes($gateway, \is_string($token) ? $token : '', $request->getClientIp())) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE);
        }
    }

    /**
     * A sign-in omniauth decided (omnibridge/symfony's OmniauthAuthenticator,
     * plan "omniauth" AU7): its own events were heard, Base\Auth\Listener\GuardSignIn
     * guarded it.
     */
    public static function byOmniauth(?\Symfony\Component\Security\Http\Authenticator\Passport\Passport $passport): bool
    {
        return null !== $passport && $passport->hasBadge(\Omnibridge\Symfony\Auth\OmniauthBadge::class);
    }

    /** Under Symfony the captcha is omnishield's form field (SecurityLoginType's guard): none without it. */
    protected function gateway(): ?string
    {
        return $this->guard->challengeGateway();
    }
}
