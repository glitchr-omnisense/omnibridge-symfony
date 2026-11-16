<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;
use Base\Entity\User\Notification;
use Base\Request\Rule\Locale;
use Base\Service\LocalizerInterface;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * The page's language on Symfony's kernel events, decided by omnibase's Locale rule (step F5e of its plan,
 * slice 2): its address, the member's own, LANG, the browser's, the site's - set on the localizer, intl and
 * the router's context -, then remembered in LANG on the answer; the visitor's time zone and country from
 * the site script's cookies.
 */
class LocalizerSubscriber implements EventSubscriberInterface
{
    public const __LANG_IDENTIFIER__ = "LANG";
    public const __TIMEZONE_IDENTIFIER__ = "TIMEZONE";

    /** The request attribute holding the language a page was shown in, for the response to remember. */
    public const DISPLAYED_LOCALE = "_base_displayed_locale";

    /** What Symfony's Request::create() says when it is told no language. */
    public const REQUEST_DEFAULT_LANGUAGE = "en-us,en;q=0.5";

    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(LocalizerInterface $localizer, AdvancedRouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->localizer = $localizer;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * A signed-in member's own language: the one their account holds, null for an account that holds none.
     * Base\Entity\User::getLocale() answers the page's for such an account (the localizer's current language: the
     * site's, or in a long-lived worker the previous request's), which, read as the member's, came before LANG and
     * the browser's: a member who never chose a language could not change the page's.
     */
    protected static function memberLocale(mixed $user): ?string
    {
        if (!$user instanceof BaseUser) {
            return null;
        }
        if (!property_exists(BaseUser::class, 'locale')) {
            return $user->getLocale();
        }
        $own = \Closure::bind(static fn (BaseUser $user): mixed => $user->locale, null, BaseUser::class)($user);

        return \is_string($own) && '' !== $own ? $own : null;
    }

    public static function getSubscribedEvents(): array
    {
        /*
         * Must be set prior SecuritySubscriber and
         * after Symfony\Component\HttpKernel\EventListener\LocaleListener::setDefaultLocale()
         *
         * CLI: php bin/console debug:event kernel.request
         */
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
            SecurityEvents::SWITCH_USER => 'onSwitchUser'
        ];
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

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        if (!is_instanceof(User::class, BaseUser::class)) {
            return;
        }
        setcookie(self::__LANG_IDENTIFIER__, $event->getTargetUser()->getLocale(), 0, "/", $this->router->getDomain());
    }

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
                (new \App\Entity\User\Notification($key, $parameters))->send($importance);
            }
            $this->localizer->setTimezone($timezone);
            if ($country = User::getCookie('country')) {
                $this->localizer->setCountry($country);
            }
        }
    }

    /** @var list<string>|null */
    private ?array $spoken = null;

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
