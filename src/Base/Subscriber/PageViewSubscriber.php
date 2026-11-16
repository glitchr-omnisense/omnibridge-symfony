<?php

namespace Base\Subscriber;

use Base\Service\Analytics;
use Base\Service\SpeculativeRequest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records one Base\Service\Analytics::track() call per trackable request.
 * Runs on kernel.terminate (after the response has already been sent) so
 * the counter writes never add latency to what the visitor is waiting on.
 *
 * Not to be confused with the pre-existing Base\Subscriber\AnalyticsSubscriber
 * (an unrelated, currently-inert - gated behind the now-dead isAdmin()
 * check - "online users" heartbeat + Google Analytics live-stats puller).
 * That one is untouched by this; naming this class PageViewSubscriber
 * specifically to avoid colliding with it.
 *
 * "Trackable" is deliberately inferred from the RESPONSE rather than
 * matched against a path allow/deny list: a successful, GET, text/html
 * response is a real page render regardless of whether it arrived via a
 * normal navigation or one of transparentJS's AJAX SPA-swap fetches (both
 * return full HTML documents with that content type) - excluding XHR
 * requests outright would have undercounted most of this app's actual
 * navigation, since in-app browsing mostly happens through those swaps,
 * not full page loads. $excludedPrefixes is the one thing that IS
 * explicitly configured (bound in services.php) - keeps admin/API/asset
 * traffic out of what's meant to be public-site analytics.
 */
class PageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Analytics $analytics,
        private readonly Security $security,
        private readonly array $excludedPrefixes = ["/admin", "/_", "/api"],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => "onKernelTerminate",
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$request->isMethod("GET")) {
            return;
        }
        // Fetched ahead by the browser, or by a client script, without the
        // visitor asking for the page: not a view. Counting these inflated
        // article views on prod for as long as the site prefetched on hover.
        if (SpeculativeRequest::is($request)) {
            return;
        }
        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES) {
            return;
        }
        if (!str_contains((string) $response->headers->get("Content-Type"), "text/html")) {
            return;
        }

        $path = $request->getPathInfo();
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // matches the Cookie global's own naming convention (@glitchr/flowjs/cookie)
        // (GROUPNAME/NAME, both uppercased) - see Analytics::track()'s
        // docblock for why this is only ever READ here, never set.
        $visitorId = $request->cookies->get("ANALYTICS/VISITOR_ID");
        $user = $this->security->getUser();

        // "" (not null) when the header is missing - Analytics::track()
        // only skips UA classification (defaults to SOURCE_HUMAN) for a
        // null $userAgent, which is for callers with no HTTP request to
        // read from at all, not for a real request that happens to omit
        // the header (itself a bot/script signal worth classifying).
        $this->analytics->track($path, $visitorId, $user?->getUserIdentifier(), $request->headers->get("User-Agent") ?? "");
    }
}
