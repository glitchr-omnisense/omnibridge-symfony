<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Api;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\RowLocks;
use Base\Entity\Thread\Like;
use Base\Enum\ThreadState;
use Base\Repository\Thread\Likes;
use Base\Repository\Threads;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\TranslatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route("/api", name: "api_")]
class ThreadController extends AbstractController
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    protected UnitOfWork $unitOfWork;

    /**
     * @var Threads<\Base\Entity\Thread>
     */
    protected $threadRepository;

    /**
     * @var Likes
     */
    protected $likeRepository;

    /** @param Threads<\Base\Entity\Thread> $threadRepository */
    public function __construct(UnitOfWork $unitOfWork, TranslatorInterface $translator, Threads $threadRepository, Likes $likeRepository)
    {
        $this->unitOfWork = $unitOfWork;  
        $this->translator = $translator;        

        $this->threadRepository = $threadRepository;
        $this->likeRepository = $likeRepository;
    }

    #[Route("/thread/{slug}/publish", name:"thread_publish", methods: ["POST"])]
    public function Publish(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::PUBLISH);
        $this->unitOfWork->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    #[Route("/thread/{slug}/hide", name:"thread_hide", methods: ["POST"])]
    public function Hide(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug);
        if (!$this->isGranted('ROLE_ADMIN')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $thread->setState(ThreadState::SECRET);
        $this->unitOfWork->flush();

        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    #[Route("/thread/{slug}/follow", name:"thread_follow", methods: ["POST"])]
    public function Follow(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug);
        if (!$this->isGranted('ROLE_USER')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $user = $this->getUser();
        \assert($user instanceof \Base\Entity\User);
        $thread->addFollower($user);
        $this->unitOfWork->flush();
        
        return JsonResponse::fromJsonString(json_encode([
            "code"    => 200,
            "response" => "OK"
        ]));
    }

    #[Route("/thread/{slug}/unfollow", name:"thread_unfollow", methods: ["POST"])]
    public function Unfollow(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug);
        if (!$this->isGranted('ROLE_USER')) {

            return JsonResponse::fromJsonString(json_encode([
                "code"    => 401,
                "response" => "Unauthorized",
            ]));
        }

        if (!$thread) throw new NotFoundHttpException();

        $user = $this->getUser();
        \assert($user instanceof \Base\Entity\User);
        $thread->removeFollower($user);
        $this->unitOfWork->flush();

        return JsonResponse::fromJsonString(json_encode(["response" => "OK"]), 200);
    }


    #[Route("/thread/{slug}/like", name:"thread_like", methods: ["POST"])]
    public function Like(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug, false) ?? throw new NotFoundHttpException();
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {

            return JsonResponse::fromJsonString(json_encode([
                "response" => "Unknown user",
                "likes" => count($thread->getLikes())
            ]), 401);
        }

        // The thread's row locked while its likes are looked at: a double
        // click, two tabs, made two Like rows (and Unlike removed one).
        Backends::of($this->unitOfWork, RowLocks::class)->locked($thread, function () use ($thread, $user): void {
            if (!$this->likeRepository->oneByThreadAndUser($thread, $user)) {
                $thread->addLike(new Like($user));
                $this->unitOfWork->flush();
            }
        });

        $nlikes = count($thread->getLikes());

        $this->addFlash("info", $this->translator->trans("@controllers.thread.like"));

        return JsonResponse::fromJsonString(json_encode([
            "response" => "OK",
            "likes" => $nlikes
        ]), 200);
    }

    #[Route("/thread/{slug}/unlike", name:"thread_unlike", methods: ["POST"])]
    public function Unlike(Request $request, string $slug): Response
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }
        $thread = $this->threadRepository->oneBySlug($slug, false) ?? throw new NotFoundHttpException();
        $nlikes = count($thread->getLikes());

        if ($this->getUser() === null) {
            return JsonResponse::fromJsonString(json_encode([
                "response" => "Unknown user",
                "likes" => $nlikes
            ]), 401);
        }

        $user = $this->getUser();
        \assert($user instanceof \Base\Entity\User);
        $like = $this->likeRepository->oneByThreadAndUser($thread, $user);
        if ($like) {
            $thread->removeLike($like);
        }

        $nlikes = count($thread->getLikes());
        $this->unitOfWork->flush();

        $this->addFlash("info", $this->translator->trans("@controllers.thread.unlike"));

        return JsonResponse::fromJsonString(json_encode([
            "response" => "OK",
            "likes" => $nlikes
        ]), 201);
    }

    /**
     * These act (publish, hide, follow, like): POST only, and the "api_thread"
     * token - in the body's _token or an X-CSRF-Token header. As plain GET
     * routes, a link or an image elsewhere published a thread for a
     * signed-in admin, and made anyone like it.
     */
    private function denied(Request $request): ?JsonResponse
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (\is_string($token) && $this->isCsrfTokenValid('api_thread', $token)) {
            return null;
        }

        return new JsonResponse(['code' => 403, 'response' => 'Invalid token'], 403);
    }
}
