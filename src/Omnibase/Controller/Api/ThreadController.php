<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Api;

use Base\Screen\Json;
use Base\Thread\ThreadActions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The threads' API under Symfony: omnibase's Base\Thread\ThreadActions decides (plan "omnibase agnostic", step F5e
 * slice 5b) - the "api_thread" token (the body's _token or an X-CSRF-Token header), who may act, the writes, the
 * flash; the answers are Symfony's (no such thread: 404).
 */
#[Route("/api", name: "api_")]
class ThreadController extends AbstractController
{
    public function __construct(protected readonly ThreadActions $actions)
    {
    }

    #[Route("/thread/{slug}/publish", name: "thread_publish", methods: ["POST"])]
    public function Publish(Request $request, string $slug): Response
    {
        return self::answer($this->actions->publish($slug, self::token($request)));
    }

    #[Route("/thread/{slug}/hide", name: "thread_hide", methods: ["POST"])]
    public function Hide(Request $request, string $slug): Response
    {
        return self::answer($this->actions->hide($slug, self::token($request)));
    }

    #[Route("/thread/{slug}/follow", name: "thread_follow", methods: ["POST"])]
    public function Follow(Request $request, string $slug): Response
    {
        return self::answer($this->actions->follow($slug, self::token($request)));
    }

    #[Route("/thread/{slug}/unfollow", name: "thread_unfollow", methods: ["POST"])]
    public function Unfollow(Request $request, string $slug): Response
    {
        return self::answer($this->actions->unfollow($slug, self::token($request)));
    }

    #[Route("/thread/{slug}/like", name: "thread_like", methods: ["POST"])]
    public function Like(Request $request, string $slug): Response
    {
        return self::answer($this->actions->like($slug, self::token($request)));
    }

    #[Route("/thread/{slug}/unlike", name: "thread_unlike", methods: ["POST"])]
    public function Unlike(Request $request, string $slug): Response
    {
        return self::answer($this->actions->unlike($slug, self::token($request)));
    }

    private static function token(Request $request): ?string
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');

        return \is_string($token) ? $token : null;
    }

    private static function answer(?Json $json): JsonResponse
    {
        return null !== $json ? new JsonResponse($json->data, $json->status) : throw new NotFoundHttpException();
    }
}
