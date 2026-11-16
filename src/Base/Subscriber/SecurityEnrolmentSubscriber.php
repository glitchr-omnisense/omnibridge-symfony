<?php

namespace Base\Subscriber;

use Base\Auth\EnrolmentPrompt;
use Base\Service\SecurityPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends a signed-in user to the enrolment prompt once, when a second factor
 * is mandatory for this account - the administrator made it so for everyone,
 * or the application requires it of one of the account's roles
 * (base.security.two_factor.required_roles) - and the account has none.
 *
 * The redirect is deliberately narrow. It only ever happens on a plain
 * top-level GET of an HTML page, so nothing in-flight is interrupted: a form
 * post lands where it was going, an XHR gets its JSON, a download downloads.
 * And it never fires on the pages the user needs in order to *answer* it -
 * the settings pages, the login and logout paths, the two-factor check
 * itself. Getting that list wrong would lock the account out of the site
 * with no way back in, so the exemptions err generously.
 *
 * The rule is Base\Auth\EnrolmentPrompt's, framework-free (step R29 of the
 * audit "lesringostars face au master plan"): omnibridge/laravel's enrolment
 * middleware asks it too.
 */
class SecurityEnrolmentSubscriber implements EventSubscriberInterface
{
    private EnrolmentPrompt $prompt;

    public function __construct(
        private SecurityPolicy $securityPolicy,
        private Security $security,
        private UrlGeneratorInterface $router,
    ) {
        $this->prompt = new EnrolmentPrompt($securityPolicy);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!EnrolmentPrompt::interrupts($request->getMethod(), $request->isXmlHttpRequest(), $request->getAcceptableContentTypes(), $request->getPathInfo())) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $user = $this->security->getUser();
        $offered = (bool) $session?->get(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT);
        if ($this->prompt->forgetsTheOffer($user, $offered)) {
            $session?->remove(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT);
            $offered = false;
        }

        $skipped = $session && $session->isStarted() && $session->get(SecurityPolicy::SESSION_ENROLMENT_SKIPPED);
        // Someone impersonating the account is asked only when it is due (needsEnrolment()), not for the offer.
        $impersonating = null !== $user && !$offered && $this->securityPolicy->needsEnrolment($user) && $this->security->isGranted('IS_IMPERSONATOR');
        if ($this->prompt->due($user, $offered, (bool) $skipped, $impersonating)) {
            $event->setResponse(new RedirectResponse($this->router->generate('user_settings_enrolment')));
        }
    }
}
