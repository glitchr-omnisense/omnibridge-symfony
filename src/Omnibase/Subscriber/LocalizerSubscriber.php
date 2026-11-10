<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;
use Base\Entity\User\Notification;
use Base\Request\Rule\Locale;
use Omnibridge\Symfony\Omnibase\Legacy\Subscriber\LocalizerSubscriber as Legacy;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

if (!class_exists(Locale::class)) {
    // omnisense/omnibase 4.x: the rule is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class LocalizerSubscriber extends Legacy
    {
    }
} else {
    /**
     * The page's language on Symfony's kernel events, decided by omnibase's Locale rule (step F5e of its plan,
     * slice 2): its address, the member's own, LANG, the browser's, the site's - set on the localizer, intl and
     * the router's context -, then remembered in LANG on the answer; the visitor's time zone and country from
     * the site script's cookies.
     */
    class LocalizerSubscriber extends Legacy
    {
        /** @var list<string>|null */
        private ?array $spoken = null;

        public static function choose(?string $address, ?string $remembered, ?string $member, bool $impersonating, ?string $browser, string $site): string
        {
            return Locale::choose($address, $remembered, $member, $impersonating, $browser, $site);
        }

        public function onKernelRequest(RequestEvent $event)
        {
            if (!$event->isMainRequest()) {
                return;
            }
            $request = $event->getRequest();
            $rule = $this->rule();
            $address = $this->router->match($request->getPathInfo())['_locale'] ?? null;
            if (null !== $rule->available($address, true)) {
                $this->localizer->markAsChanged();
            }
            $token = $this->tokenStorage->getToken();
            $user = $token?->getUser();
            $browser = $rule->browser($request->headers->get('Accept-Language'), static fn (array $languages): ?string => $request->getPreferredLanguage($languages));
            $locale = $rule->decide($address, $request->cookies->get(self::__LANG_IDENTIFIER__), self::memberLocale($user), $token instanceof SwitchUserToken, $browser, $this->localizer::getDefaultLocale() ?? $this->localizer->getLocale());

            $this->localizer->setLocale($locale, $request);
            if (class_exists(\Locale::class)) {
                \Locale::setDefault(str_replace('-', '_', $locale));
            }
            $this->localizer->markAsLate();
            $request->attributes->set(self::DISPLAYED_LOCALE, $locale);
            $this->router->getContext()->setParameter('_locale', $locale);

            $this->localizer->setTimezone('UTC');
            if (is_instanceof(User::class, BaseUser::class)) {
                [$timezone, $decision] = Locale::timezone(User::getCookie('timezone'));
                foreach ($decision->notices as [$key, $parameters, $importance]) {
                    (new Notification($key, $parameters))->send($importance);
                }
                $this->localizer->setTimezone($timezone);
                if ($country = User::getCookie('country')) {
                    $this->localizer->setCountry($country);
                }
            }
        }

        public function onKernelResponse(ResponseEvent $event): void
        {
            $request = $event->getRequest();
            $locale = $request->attributes->get(self::DISPLAYED_LOCALE);
            if (!$event->isMainRequest() || !\is_string($locale)) {
                return;
            }
            $response = $event->getResponse();
            $set = [] !== array_filter($response->headers->getCookies(), static fn (Cookie $cookie): bool => self::__LANG_IDENTIFIER__ === $cookie->getName());
            foreach (Locale::remember($locale, $request->cookies->get(self::__LANG_IDENTIFIER__), $set)->cookies as [$name, $value]) {
                $response->headers->setCookie(Cookie::create($name, $value, 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
            }
        }

        /** The rule over the languages this site speaks: the translator's, and those its routes have an address in ("app_legal.de"). */
        private function rule(): Locale
        {
            if (null === $this->spoken) {
                $languages = $this->localizer->getAvailableLocaleLangs();
                $generator = $this->router->getGenerator();
                if (method_exists($generator, 'getCompiledRoutes')) {
                    foreach (array_keys($generator->getCompiledRoutes()) as $name) {
                        if (preg_match('/\.([a-z]{2})$/', (string) $name, $found)) {
                            $languages[] = $found[1];
                        }
                    }
                }
                $this->spoken = array_values(array_unique(array_filter($languages)));
            }

            return new Locale($this->spoken, fn (string $locale): string => $this->localizer::normalizeLocale($locale));
        }
    }
}
