<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use App\Repository\UserRepository;
use Base\Auth\Policy\Restriction;
use Base\Auth\Policy\RestrictionDecision;
use Base\Auth\Policy\Welcome;
use Base\Entity\User as BaseUser;
use Base\Entity\User\Notification;
use Base\Request\Decision;
use Base\Request\RouterPage;
use Base\Request\Rule\Accounts;
use Base\Request\Rule\Approval;
use Base\Request\Rule\Kicked;
use Base\Request\Rule\LaunchPage;
use Base\Request\Rule\MaintenancePage;
use Base\Request\Rule\Presence;
use Base\Request\Rule\Verification;
use Base\Service\LauncherInterface;
use Base\Service\Localizer;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\ReferrerInterface;
use Base\Service\SettingBagInterface;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Symfony\Omnibase\Legacy\Subscriber\SecuritySubscriber as Legacy;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Omnibridge\Symfony\Omnibase\Security\RescueFormAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

if (!class_exists(Accounts::class)) {
    // omnisense/omnibase 4.x: the rules are still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class SecuritySubscriber extends Legacy
    {
    }
} else {
    /**
     * omnibase's request rules on Symfony's kernel events (step F5e of its plan): the access restriction and
     * the word of welcome (slice 1: Base\Auth\Policy\{Restriction, Welcome}); the maintenance and launch pages
     * and the account rules - impersonation, kick, presence, verification, approval (slice 2:
     * Base\Request\Rule\*). Each decision is applied here; nothing is decided here.
     */
    class SecuritySubscriber extends Legacy
    {
        public function __construct(
            \Base\Repository\Users $userRepository, AuthorizationChecker $authorizationChecker, TokenStorageInterface $tokenStorage,
            RequestStack $requestStack, ReferrerInterface $referrer, SettingBagInterface $settingBag, Localizer $localizer,
            AdvancedRouterInterface $router, ParameterBagInterface $parameterBag, MaintenanceProviderInterface $maintenanceProvider,
            LauncherInterface $launcher, ?Profiler $profiler = null, ?Tokens $tokens = null,
            private readonly ?UnitOfWork $unitOfWork = null, private readonly ?Urls $urls = null,
        ) {
            parent::__construct($userRepository, $authorizationChecker, $tokenStorage, $requestStack, $referrer, $settingBag, $localizer, $router, $parameterBag, $maintenanceProvider, $launcher, $profiler, $tokens);
        }

        public function onAccessRequest(?RequestEvent $event = null): bool
        {
            if (!$this->router->getRouteFirewall()?->isSecurityEnabled() || !$event->isMainRequest() || $this->router->isWdt($event)) {
                return true;
            }

            $user = $this->tokenStorage->getToken()?->getUser();
            $decision = (new Restriction(fn (string $attribute, mixed $subject = null): bool => $this->authorizationChecker->isGranted($attribute, $subject)))
                ->decide($user, $this->page($event, true));

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

        public function onKernelRequest(RequestEvent $event)
        {
            $token = $this->tokenStorage->getToken();
            if (!$token?->getUser() instanceof BaseUser) {
                return;
            }
            $switch = $token instanceof SwitchUserToken ? ($this->router->getRouteFirewall()?->getSwitchUser()['parameter'] ?? null) : null;
            $this->apply($this->accounts()->request($token?->getUser(), $this->page($event), fn (): bool => $this->granted('ROLE_ADMIN'), $event->isMainRequest(), $token instanceof SwitchUserToken, $switch), $event);
        }

        public function onKernelResponse(ResponseEvent $event)
        {
            $this->accounts()->response($this->tokenStorage->getToken()?->getUser());
        }

        public function onMaintenanceRequest(RequestEvent $event)
        {
            if ($event->isMainRequest()) {
                $this->apply((new MaintenancePage($this->maintenanceProvider, $this->parameterBag->get('base.site.maintenance.redirect_on_deny'), $this->parameterBag->get('base.site.index')))->decide($this->page($event), $this->granted(...)), $event);
            }
        }

        public function onLaunchRequest(RequestEvent $event)
        {
            if ($event->isMainRequest()) {
                $this->apply((new LaunchPage($this->launcher, (bool) $this->settingBag->getScalar('base.settings.launchdate.redirect_on_deny'), $this->parameterBag->get('base.site.index')))->decide($this->page($event), $this->granted(...), $this->localizer->getLocale()), $event);
            }
        }

        private function granted(string $attribute): bool
        {
            return $this->authorizationChecker->isGranted($attribute);
        }

        private function accounts(): Accounts
        {
            return new Accounts(new Kicked($this->tokens), new Presence($this->unitOfWork), new Verification($this->urls), new Approval($this->unitOfWork, (bool) $this->parameterBag->get('base.user.register.autoapprove')));
        }

        /** A rule's decision applied to the event: notices, the address kept, the sign-out, the profiler, the answer. */
        private function apply(Decision $decision, RequestEvent $event): void
        {
            foreach ($decision->notices as [$key, $parameters, $importance]) {
                (new Notification($key, $parameters))->send($importance);
            }
            if (null !== $decision->remember) {
                $this->referrer->setUrl($decision->remember);
            }
            $user = $this->tokenStorage->getToken()?->getUser();
            if ($decision->signOut && \is_object($user) && method_exists($user, 'Logout')) {
                $user->Logout();
            }
            if (!$decision->profiler) {
                $this->profiler?->disable();
            }
            if (Decision::DENY === $decision->kind) {
                throw new HttpException($decision->status);
            }
            if (Decision::REDIRECT === $decision->kind) {
                $event->setResponse(new RedirectResponse($decision->url ?? $this->router->generate($decision->route, $decision->parameters), $decision->status));
            }
            if ($decision->final) {
                $event->stopPropagation();
            }
        }

        /** @param bool $restriction whether the restriction's routes are asked (onAccessRequest()) */
        private function page(RequestEvent $event, bool $restriction = false): RouterPage
        {
            $redirects = $restriction ? array_map(fn (string $route): string => str_rstrip($route, '.'.$this->localizer->getDefaultLocaleLang()), $this->settingBag->getScalar('base.settings.access_restriction.redirect_on_deny') ?? []) : [];

            return new RouterPage($this->router, $event->getRequest()->getUri(), array_values($redirects), static fn (): bool => RescueFormAuthenticator::isSecurityRoute($event->getRequest()));
        }
    }
}
