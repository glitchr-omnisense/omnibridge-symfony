<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Request\LocaleSwitch;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\ReferrerInterface;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Base\Subscriber\LocalizerSubscriber;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The language menu under Symfony: omnibase's Base\Request\LocaleSwitch decides (plan "omnibase agnostic", step
 * F5e slice 5) - the language, the member's own, the page to go back to -, the same omnibridge/laravel's
 * SwitchLocale asks; the route matched and generated, the flash and the cookie are Symfony's.
 */
class LocalizerController extends AbstractController
{
    public function __construct(
        protected LocalizerInterface $localizer,
        protected UnitOfWork $unitOfWork,
        protected AdvancedRouterInterface $router,
        protected ReferrerInterface $referrer,
        protected TranslatorInterface $translator,
        protected LocaleSwitch $switch,
    ) {
    }

    /**
     * The visitor's choice of language: remembered (LANG, and a member's own
     * locale), then the page they came from, in that language when it has an
     * address of its own. Without one ("/locale"), the choice is forgotten.
     */
    #[Route("/locale/{_locale}", name: "_switch_locale")]
    public function Locale(Request $request, ReferrerInterface $referrer, ?string $_locale = null)
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
            $response->headers->clearCookie(LocalizerSubscriber::__LANG_IDENTIFIER__, "/");
        }

        return $response;
    }

    #[Route("/timezone/{_timezone}", name: "_switch_timezone")]
    public function Timezone(Request $request, ReferrerInterface $referrer, ?string $_locale = null)
    {
        if ($_locale === null) {
            setcookie(LocalizerSubscriber::__LANG_IDENTIFIER__, null);
        }

        $referrer->setUrl($_SERVER["HTTP_REFERER"] ?? null);
        $referrerName = $this->router->getRouteName(strval($referrer));
        $referrerParameters = array_filter($this->router->match(strval($referrer)), fn($a) => !str_starts_with($a, "_"), ARRAY_FILTER_USE_KEY);
        $referrer->setUrl(null);

        $availableLocales = array_merge($this->localizer->getAvailableLocaleLangs(), $this->localizer->getAvailableLocales());
        if (in_array($_locale, $availableLocales)) {
            setcookie('_locale', $_locale);
            if (!$this->isGranted("IS_IMPERSONATOR")) {
                $this->getUser()?->setLocale($this->localizer->getLocale($_locale));
                $this->unitOfWork->flush();
            }

            $this->addFlash("info", $this->translator->trans("@controllers.locale_changeto.action", [$this->localizer->getLocaleLangName($_locale)]));

            if (!str_starts_with($referrerName, "_switch_timezone")) {
                $referrer->setUrl(null);
                $lang = $_locale ? "." . $this->localizer->getLocaleLang($_locale) : "";

                try {
                    return $this->redirect($this->router->generate($referrerName . $lang, $referrerParameters));
                } catch (RouteNotFoundException $e) {
                    return $this->redirect($this->router->generate($referrerName, $referrerParameters));
                }
            }
        }

        return $this->redirect($this->router->getUrlIndex());
    }
}
