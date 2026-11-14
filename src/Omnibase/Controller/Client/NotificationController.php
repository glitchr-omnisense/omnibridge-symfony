<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Auth\Account\Outcome;
use Base\Bridge\Symfony\AccountScreens;
use Omnibridge\Symfony\Omnibase\ScreenResponder;
use Base\Notifier\NotificationCenter;
use Base\Screen\Json;
use Base\Screen\View;
use Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The notification center under Symfony: omnibase's Base\Notifier\NotificationCenter decides (plan "omnibase agnostic",
 * step F5e slice 5b) - the page's notifications, the push subscriptions by their token, the service worker's source -;
 * the answers are Symfony's. The bell's payload, read marks and removals: Base\Controller\Action\Notification.
 */
class NotificationController extends AbstractController
{
    use AnswersOutcomes;

    public const CSRF = NotificationCenter::TOKEN;

    public function __construct(protected readonly NotificationCenter $center)
    {
    }

    /**
     * The list: its template and its view model (Base\Screen\Account\NotificationsPage) to omnibase's ScreenResponder;
     * a template the site overrides gets `notifications`, `unread` and `unreadOnly` as well (deprecated).
     */
    #[Route("/notifications", name: "user_notifications")]
    public function Index(Request $request, ScreenResponder $responder, AccountScreens $screens): Response
    {
        $unreadOnly = $request->query->getBoolean("unread");
        $page = $this->center->page($unreadOnly);
        if ($page instanceof Outcome) {
            return $this->answer($page);
        }
        [$notifications, $unread] = $page;
        $template = NotificationCenter::TEMPLATE;

        return $responder->respond($request, new View($template, $screens->notifications($notifications, $unread, $unreadOnly), "Account/Notifications"), $responder->legacy($template, fn () => [
            "notifications" => $notifications,
            "unread" => $unread,
            "unreadOnly" => $unreadOnly,
        ]));
    }

    /** What the browser needs before it can subscribe: the VAPID public key, or null when push is off. */
    #[Route("/push/config", name: "push_config", methods: ["GET"])]
    public function PushConfig(): JsonResponse
    {
        return self::answerJson($this->center->pushConfig());
    }

    /** Body: {token, subscription: PushSubscriptionJSON} */
    #[Route("/push/subscribe", name: "push_subscribe", methods: ["POST"])]
    public function PushSubscribe(Request $request): JsonResponse
    {
        return self::answerJson($this->center->subscribe(NotificationCenter::body($request->getContent(), $request->request->all()), $request->headers->get("User-Agent")));
    }

    /** Body: {token, endpoint} */
    #[Route("/push/unsubscribe", name: "push_unsubscribe", methods: ["POST"])]
    public function PushUnsubscribe(Request $request): JsonResponse
    {
        return self::answerJson($this->center->unsubscribe(NotificationCenter::body($request->getContent(), $request->request->all())));
    }

    /**
     * The service worker, served from the origin's root (registered from /sw.js it covers the whole site, admin
     * included), by PHP so that omnibase owns it; no-cache, so a new version is picked up on the next check.
     */
    #[Route("/sw.js", name: "push_service_worker", methods: ["GET"])]
    public function ServiceWorker(): Response
    {
        return new Response(NotificationCenter::serviceWorker(), 200, [
            "Content-Type" => "application/javascript; charset=UTF-8",
            "Cache-Control" => "no-cache",
            "Service-Worker-Allowed" => "/",
        ]);
    }

    private static function answerJson(Json $json): JsonResponse
    {
        return new JsonResponse($json->data, $json->status);
    }
}
