<?php

namespace Base\Subscriber;

use Base\Database\Attribute\Uploader;
use Base\Service\Localizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Clears static state that is really request-scoped, for processes that outlive
 * a request: FrankenPHP worker mode and messenger consumers.
 *
 * Under FPM, or FrankenPHP classic mode, statics die with the request anyway and
 * both hooks below are no-ops on already-empty state.
 *
 * Two hooks, because neither covers every mode on its own:
 *
 *  - kernel.reset (reset()): the services_resetter runs between requests handled
 *    by the SAME kernel, and messenger resets services between messages. But
 *    under FRANKENPHP_RESET_KERNEL=1 the kernel is cloned after each request:
 *    Kernel::__clone() sets resetServices back to false and the clone boots a
 *    fresh container, so the resetter never runs at all - while class statics
 *    survive the clone untouched.
 *
 *  - kernel.request, main request only, top priority: fires in every mode,
 *    cloned kernel included, and ahead of LocalizerSubscriber (priority 8) and
 *    anything else that reads this state.
 */
class RequestScopedStateSubscriber implements EventSubscriberInterface, ResetInterface
{
    public const PRIORITY = 4096;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', self::PRIORITY],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // A sub-request (forward, ESI, error rendering) belongs to the request that
        // is still running: clearing here would pull temporary files out from under it.
        if (!$event->isMainRequest()) {
            return;
        }

        $this->reset();
    }

    public function reset(): void
    {
        Uploader::forgetTemporaryFiles();
        Localizer::forgetRequestState();
    }
}
