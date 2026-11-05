<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use Base\Auth\Policy\Page;
use Base\Auth\Policy\Restriction;
use Base\Auth\Policy\RestrictionDecision;
use Base\Auth\Policy\Welcome;
use Base\Entity\User as BaseUser;
use Base\Entity\User\Notification;
use Omnibridge\Symfony\Omnibase\Legacy\Subscriber\SecuritySubscriber as Legacy;
use Omnibridge\Symfony\Omnibase\Security\RescueFormAuthenticator;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

if (!class_exists(Restriction::class)) {
    // omnisense/omnibase 4.x: the rules are still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class SecuritySubscriber extends Legacy
    {
    }
} else {
    /**
     * The access restriction and the word of welcome, decided by omnibase's Restriction and Welcome policies
     * (step F5e of its plan, slice 1); the request's other rules (a kicked account, the verification, the
     * approval, the presence) are still the former subscriber's, until slice 2.
     */
    class SecuritySubscriber extends Legacy
    {
        public function onAccessRequest(?RequestEvent $event = null): bool
        {
            if (!$this->router->getRouteFirewall()?->isSecurityEnabled() || !$event->isMainRequest() || $this->router->isWdt($event)) {
                return true;
            }

            $user = $this->tokenStorage->getToken()?->getUser();
            $decision = (new Restriction(fn (string $attribute, mixed $subject = null): bool => $this->authorizationChecker->isGranted($attribute, $subject)))
                ->decide($user, $this->page($event));

            if (!$decision->profiler && $this->profiler && !$this->router->isDebug()) {
                $this->profiler->disable();
            }
            if (null !== $decision->notice) {
                (new Notification($decision->notice[0]))->send($decision->notice[1]);
            }
            if (RestrictionDecision::NOT_FOUND === $decision->kind) {
                throw new NotFoundHttpException();
            }
            if (RestrictionDecision::REDIRECT === $decision->kind) {
                $response = $decision->routes ? $this->router->redirect(first($decision->routes) ?? $this->router->getRoute(RescueFormAuthenticator::PENDING_ROUTE)) : null;
                $event->setResponse($response ?? $this->router->redirect(RescueFormAuthenticator::LOGIN_ROUTE));
                $event->stopPropagation();

                return false;
            }

            return true;
        }

        public function onLoginSuccess(LoginSuccessEvent $event)
        {
            $user = $event->getUser();
            if (!$user instanceof BaseUser) {
                return;
            }
            $authenticated = !$user->isPersistent() || !$user->isVerified() || $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY') || $this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED');
            if (null !== $key = Welcome::key($user, $authenticated)) {
                (new Notification($key, [$user]))->send('success');
            }
        }

        private function page(RequestEvent $event): Page
        {
            return new class($this->router, $this->settingBag, $this->localizer, $event) implements Page {
                public function __construct(private $router, private $settings, private $localizer, private RequestEvent $event)
                {
                }

                public function isSecurity(): bool
                {
                    return RescueFormAuthenticator::isSecurityRoute($this->event->getRequest());
                }

                public function isProfiler(): bool
                {
                    return $this->router->isProfiler();
                }

                public function isAdmin(): bool
                {
                    return $this->router->isAdmin();
                }

                public function name(): ?string
                {
                    return $this->router->getRouteName();
                }

                public function redirectsOnDeny(): array
                {
                    $routes = $this->settings->getScalar('base.settings.access_restriction.redirect_on_deny') ?? [];
                    foreach ($routes as $i => $route) {
                        $routes[$i] = str_rstrip($route, '.'.$this->localizer->getDefaultLocaleLang());
                    }

                    return $routes;
                }
            };
        }
    }
}
