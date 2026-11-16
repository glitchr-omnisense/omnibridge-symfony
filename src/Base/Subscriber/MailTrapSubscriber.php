<?php

namespace Base\Subscriber;

use Base\Mailer\MailTrap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;

/**
 * Stops mail at the last possible moment when the trap is armed.
 *
 * MessageEvent fires twice for a queued message: once as it is handed to
 * Messenger (isQueued() true) and again when the worker actually sends it.
 * Only the second one is intercepted, and that is the whole point - trapping
 * at queue time would refuse mail that a released trap should still deliver,
 * while trapping at send time means a message queued before the incident is
 * still caught on its way out.
 */
class MailTrapSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MailTrap $trap)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After Symfony's own listeners (envelope rewriting, DKIM), so what is
        // captured is the message as it would really have gone out - including
        // any recipient rewriting - rather than an earlier draft of it.
        return [MessageEvent::class => ['onMessage', -1000]];
    }

    public function onMessage(MessageEvent $event): void
    {
        if ($event->isQueued() || $event->isRejected() || $this->trap->isBypassed() || !$this->trap->isArmed()) {
            return;
        }

        $this->trap->capture($event->getMessage(), $event->getEnvelope());

        // reject() stops delivery without raising, so the worker marks the
        // message handled and it leaves the queue. That is deliberate: the
        // copy on disk is now the record, and letting it fail instead would
        // pile the same mail into the retry/failed transports where a purge
        // could no longer tell one message from another.
        $event->reject();
    }
}
