<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use Base\Entity\User\Notification;
use Base\Enum\UserRole;
use Base\Notifier\Notifier;
use Base\Notifier\NotifierInterface;
use Base\Service\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NotifierSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * @var NotifierInterface
     */
    protected NotifierInterface $notifier;

    /** * @var bool */
    protected bool $debug;

    public function __construct(Notifier $notifier, AuthorizationCheckerInterface $authorizationChecker, ParameterBagInterface $parameterBag, string $debug)
    {
        $this->debug = $debug;
        $this->notifier = $notifier;
        $this->authorizationChecker = $authorizationChecker;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest']
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!$this->authorizationChecker->isGranted(UserRole::SUPERADMIN)) {
            return;
        }

        $notification = null;
        if ($this->debug && !$this->notifier->hasLoopback()) {
            $notification = new Notification("@notifications.notifier.no_loopback");
        }
        if (!$this->debug && $this->notifier->hasLoopback()) {
            $notification = new Notification("@notifications.notifier.no_debug", array_keys(mailparse($this->notifier->getTechnicalRecipient()->getEmail())));
        }

        $notification?->send("warning");
    }
}
