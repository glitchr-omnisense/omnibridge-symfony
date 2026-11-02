<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Security\UserTracker;
use Base\Service\SecurityPolicy;
use Base\Repository\User\Connections;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Reacts to an account being signed into from a browser it has not been
 * signed into before: tells the user by email (administrator's switch), and
 * on an account with no second factor, flags the session so the next page
 * offers - once, optionally - to set up a one-time code
 * (SecurityEnrolmentSubscriber, Base\Controller\Action\Settings\Enrolment).
 *
 * "Browser" here means what UserTracker already means by it: a Connection,
 * keyed by the long-lived id it keeps in a cookie. A sign-in that has to
 * create one is a sign-in from somewhere new. The account's very first
 * sign-in creates one too, and there is nothing useful to warn about then -
 * the person is holding the device in their hand - so the mail goes out only
 * once a second browser appears.
 *
 * Nothing here blocks the login. This is a notice after the fact, not a
 * challenge; the administrator's switch decides whether it is sent at all.
 */
class NewDeviceSubscriber implements EventSubscriberInterface
{
    /**
     * A Connection created within this many seconds of the sign-in is one
     * this sign-in created. Generous enough to survive a slow request,
     * far short of anything that could catch a returning browser.
     */
    private const FRESH_SECONDS = 120;

    public function __construct(
        private SecurityPolicy $securityPolicy,
        private UserTracker $userTracker,
        private Connections $connectionRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // omniauth's sign-ins are Base\Auth\Listener\WarnNewDevice's, after TrackConnections made the connection.
        if (\Base\Security\SignInGuard::byOmniauth($event->getPassport())) {
            return;
        }
        $email = $this->securityPolicy->isNewDeviceEmailEnabled();
        $prompt = $this->securityPolicy->isNewDevicePromptEnabled();
        if (!$email && !$prompt) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $connection = $this->userTracker->getCurrentConnection($user, false);
        if (null === $connection || null === $connection->getCreatedAt()) {
            return;
        }

        if (time() - $connection->getCreatedAt()->getTimestamp() > self::FRESH_SECONDS) {
            return; // a browser this account has used before
        }

        // Nothing to compare a first-ever sign-in against.
        if ($this->connectionRepository->countFor($user) < 2) {
            return;
        }

        if ($email) {
            $notification = new Notification('newDevice.subject', [$user]);
            $notification->setUser($user);
            $notification->setHtmlTemplate('@Base/client/email/security_newDevice.html.twig', [
                'connection' => $connection,
                'recipient' => $user,
            ]);
            $notification->send('email');
        }

        // The offer is made once per person, not once per browser: a
        // "no thanks" is kept in a long-lived cookie, and an account that
        // already holds a second factor has nothing to be offered.
        $request = $event->getRequest();
        if ($prompt
            && !$this->securityPolicy->hasSecondFactor($user)
            && !$request->cookies->has(SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED)
            && $request->hasSession()) {
            $request->getSession()->set(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, true);
        }
    }
}
