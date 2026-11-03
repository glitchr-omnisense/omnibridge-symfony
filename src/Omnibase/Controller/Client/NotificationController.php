<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use App\Entity\User;
use Base\Bridge\Symfony\AccountScreens;
use Base\Bridge\Symfony\ScreenResponder;
use Base\Entity\User\Notification;
use Base\Notifier\NotificationLinkerInterface;
use Base\Repository\User\Notifications;
use Base\Screen\View;
use Base\Service\Push\WebPushService;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The notification center's HTTP surface: the list page, the push
 * subscription registry, and the service worker itself (the bell's payload,
 * read marks and removals: Base\Controller\Action\Notification).
 *
 * Every mutation is a JSON POST carrying the "notifications" CSRF token in
 * its body (the toolbar renders it as data-csrf), same-origin only. They
 * answer JSON rather than redirecting because the bell updates in place.
 */
class NotificationController extends AbstractController
{
    public const CSRF = "notifications";

    protected readonly UnitOfWork $unitOfWork;

    public function __construct(
        UnitOfWork $unitOfWork,
        protected readonly Notifications $notificationRepository,
        protected readonly WebPushService $webPush,
        protected readonly CsrfTokenManagerInterface $csrfTokenManager,
        protected readonly ?NotificationLinkerInterface $linker = null,
    ) {
        $this->unitOfWork = $unitOfWork;
    }

    /**
     * The list: its template and its view model
     * (Base\Screen\Account\NotificationsPage, plan "omnibase agnostique", C5)
     * to omnibase's ScreenResponder - the days' groups by the screen's clock,
     * the token in it, the answers stored by no cache; a template the site
     * overrides gets `notifications`, `unread` and `unreadOnly` as well
     * (deprecated, gone in 4.0).
     */
    #[Route("/notifications", name: "user_notifications")]
    public function Index(Request $request, ScreenResponder $responder, AccountScreens $screens): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute("security_login");
        }

        // ?unread=1 narrows the page to what is still waiting. Filtering in
        // the query rather than in the template matters: the list is capped
        // at 100, so hiding read rows client-side would show an empty page to
        // anyone whose last hundred notifications happen to be read, while
        // older unread ones exist.
        $unreadOnly = $request->query->getBoolean("unread");
        $notifications = $this->notificationRepository->findVisibleFor($user, 100, 0, $unreadOnly);

        $unread = $this->countUnread($user);
        $template = "client/user/notifications.html.twig";

        return $responder->respond($request, new View($template, $screens->notifications($notifications, $unread, $unreadOnly), "Account/Notifications"), $responder->legacy($template, fn () => [
            "notifications" => $notifications,
            "unread" => $unread,
            "unreadOnly" => $unreadOnly,
        ]));
    }

    // The bell's payload (/notifications/latest) is an action written once for every host:
    // Base\Controller\Action\Notification\Latest (audit "lesringostars face au master plan", R27).

    // The bell's read marks and removals (/notifications/read, /notifications/delete) are actions written
    // once for every host too: Base\Controller\Action\Notification\Read and Delete (R29).

    /** What the browser needs before it can subscribe: the VAPID public key, or null when push is off. */
    #[Route("/push/config", name: "push_config", methods: ["GET"])]
    public function PushConfig(): JsonResponse
    {
        return new JsonResponse([
            "enabled" => $this->webPush->isConfigured() && $this->getUser() instanceof User,
            "publicKey" => $this->webPush->getPublicKey(),
        ]);
    }

    /** Body: {token, subscription: PushSubscriptionJSON} */
    #[Route("/push/subscribe", name: "push_subscribe", methods: ["POST"])]
    public function PushSubscribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $vars = $this->body($request);
        if (!$user instanceof User || !$this->csrfOk($vars)) {
            return new JsonResponse(["success" => false], 403);
        }
        if (!$this->webPush->isConfigured()) {
            return new JsonResponse(["success" => false, "error" => "Push is not configured."], 503);
        }

        try {
            $this->webPush->subscribe($user, (array) ($vars["subscription"] ?? []), $request->headers->get("User-Agent"));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(["success" => false, "error" => $e->getMessage()], 400);
        }

        return new JsonResponse(["success" => true]);
    }

    /** Body: {token, endpoint} */
    #[Route("/push/unsubscribe", name: "push_unsubscribe", methods: ["POST"])]
    public function PushUnsubscribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $vars = $this->body($request);
        if (!$user instanceof User || !$this->csrfOk($vars)) {
            return new JsonResponse(["success" => false], 403);
        }

        $removed = $this->webPush->unsubscribe($user, (string) ($vars["endpoint"] ?? ""));
        return new JsonResponse(["success" => true, "removed" => $removed]);
    }

    /**
     * The service worker. Served from the origin root because a worker's
     * scope cannot exceed its own path: registered from /sw.js it covers the
     * whole site, admin included. Served by PHP rather than as a static
     * file so the bundle owns it - the app's public/ never has to carry a
     * copy. no-cache so a new version is picked up on the next check.
     */
    #[Route("/sw.js", name: "push_service_worker", methods: ["GET"])]
    public function ServiceWorker(): Response
    {
        $source = file_get_contents(\Omnibridge\Symfony\Omnibase\BaseBundle::getBundleDir() . "/src/Resources/sw/sw.js");
        return new Response($source, 200, [
            "Content-Type" => "application/javascript; charset=UTF-8",
            "Cache-Control" => "no-cache",
            "Service-Worker-Allowed" => "/",
        ]);
    }

    protected function body(Request $request): array
    {
        $vars = json_decode($request->getContent(), true);
        return is_array($vars) ? $vars : $request->request->all();
    }

    protected function csrfOk(array $vars): bool
    {
        return !empty($vars["token"]) && $this->isCsrfTokenValid(self::CSRF, (string) $vars["token"]);
    }

    protected function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadFor($user);
    }

    public function serialize(Notification $n): array
    {
        return [
            "id" => $n->getId(),
            "title" => $n->getTitle() ?: $n->getSubject(),
            "content" => strip_tags($n->getContent()),
            "url" => $n->getUrl(),
            // The back-office page for the same target, when an admin is
            // installed and the notification knows what it is about; the
            // client picks it in the back-office (data-notifications-context).
            "adminUrl" => $this->linker?->adminUrl($n),
            "importance" => $n->getImportance(),
            "isRead" => $n->isRead(),
            "sentAt" => $n->getSentAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
