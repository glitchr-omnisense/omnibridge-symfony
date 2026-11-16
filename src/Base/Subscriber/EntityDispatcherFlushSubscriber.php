<?php

namespace Base\Subscriber;

use Base\EntityDispatcher\AbstractEventDispatcher;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Flushes what the entity dispatchers' listeners changed (a thread
 * published by its "publishable" event, a user's welcome state...), once the
 * flush that raised those events is completely over: when the response goes
 * out, when a command ends, after each message a worker handles. See
 * AbstractEventDispatcher::$flushPending for why not from postFlush.
 *
 * The dispatchers built in this process alone (AbstractEventDispatcher::live()):
 * one never built has nothing pending, and building it then - from the tagged
 * services, at the end of every command - required its file from the container's
 * folder, which bin/console cache:clear had just removed: the command died at
 * console.terminate ("Failed opening required .../getNewsletterEventDispatcherService.php").
 */
final class EntityDispatcherFlushSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the response's other listeners (SecuritySubscriber's
            // poke flushes too): their flush then finds nothing left over.
            KernelEvents::RESPONSE => ['onResponse', 64],
            ConsoleEvents::TERMINATE => ['flush', 64],
            WorkerMessageHandledEvent::class => ['flush', 64],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach (AbstractEventDispatcher::live() as $dispatcher) {
            $dispatcher->flushPending();
        }
    }
}
