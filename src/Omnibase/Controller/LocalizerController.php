<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Request\LocaleSwitch;
use Base\Request\TimezoneSwitch;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The language menu and the time zone's choice under Symfony: omnibase's Base\Request\LocaleSwitch and TimezoneSwitch
 * decide (plan "omnibase agnostic", step F5e slices 5a and 5b) - the language or the zone, the member's own, the page
 * to go back to -, the same omnibridge/laravel's SwitchLocale asks; the route matched and generated, the flash and
 * the cookies are Symfony's.
 */
class LocalizerController extends AbstractController
{
    public function __construct(
        protected AdvancedRouterInterface $router,
        protected LocaleSwitch $switch,
        protected TimezoneSwitch $timezones,
    ) {
    }

    /**
     * The visitor's choice of language: remembered (LANG, and a member's own
     * locale), then the page they came from, in that language when it has an
     * address of its own. Without one ("/locale"), the choice is forgotten.
     */
    #[Route("/locale/{_locale}", name: "_switch_locale")]
    public function Locale(Request $request, ?string $_locale = null)
    {
        $locale = $this->switch->choose($_locale);
        if (null === $locale) {
            $response = $this->redirect($this->router->getUrlIndex());
        } else {
            $this->addFlash("info", $this->switch->remember((string) $_locale, $locale));
            $path = LocaleSwitch::referred($request->headers->get("referer"), $request->getHost());
            foreach ($this->switch->back(null !== $path ? $this->router->getRouteMatch($path) : null, (string) $_locale) as [$name, $parameters]) {
                try {
                    $response = $this->redirect($this->router->generate($name, $parameters));
                    break;
                } catch (RouteNotFoundException) {
                }
            }
            $response ??= $this->redirect($this->router->getUrlIndex());
            // The choice, for every page that has no language of its own in its address.
            $response->headers->setCookie(Cookie::create(LocaleSwitch::COOKIE, $locale, 0, "/", null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
        }
        if ($_locale === null) {
            $response->headers->clearCookie(LocaleSwitch::COOKIE, "/");
        }

        return $response;
    }

    /**
     * The visitor's time zone ("/timezone/Europe/Paris"): remembered (LOCATION/TIMEZONE, and a member's own), then the
     * page they came from; a zone PHP does not know, the index; none ("/timezone"), the choice forgotten.
     */
    #[Route("/timezone/{_timezone}", name: "_switch_timezone", requirements: ["_timezone" => ".+"])]
    public function Timezone(Request $request, ?string $_timezone = null): Response
    {
        $timezone = TimezoneSwitch::choose($_timezone);
        if (null === $timezone) {
            $response = $this->redirect($this->router->getUrlIndex());
            if (null === $_timezone) {
                $response->headers->clearCookie(TimezoneSwitch::COOKIE, "/");
            }

            return $response;
        }
        $this->timezones->remember($timezone);
        $response = $this->redirect(TimezoneSwitch::back($request->headers->get("referer"), $request->getHost()) ?? $this->router->getUrlIndex());
        $response->headers->setCookie(Cookie::create(TimezoneSwitch::COOKIE, $timezone, 0, "/", null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));

        return $response;
    }
}
