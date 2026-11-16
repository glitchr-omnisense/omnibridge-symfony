<?php

namespace Base\Controller\UX;

use Base\Field\Editor\EditorJs;
use Base\Field\Search\Answer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An Editor.js field's routes under Symfony: Base\Field\Editor\EditorJs answers each action (plan "omnibase
 * agnostic", step F5d, slice 6), the same omnibridge/laravel's EditorController asks - the mentions
 * (endpointBy*), the pictures (uploadBy*), the collaboration's ticket and autosave. The mentions' searches keep
 * the profiler off, as before. A site's own controller (App\Controller\UX\EditorController) still narrows a
 * mention's search to its fields ($fields: a user's username, first and last names).
 */
#[ Route(priority: -1, name: "ux_editorjs_") ]
class EditorController extends AbstractController
{
    public const STATUS_OK = EditorJs::STATUS_OK;
    public const STATUS_BAD = EditorJs::STATUS_BAD;
    public const STATUS_NOTOKEN = EditorJs::STATUS_NOTOKEN;

    public function __construct(protected EditorJs $editorJs, protected ?Profiler $profiler = null)
    {
    }

    #[Route("/ux/editorjs/user/{data}/{page}", name:"endpointByUser")]
    public function EndpointByUser(Request $request, $data = null, array $fields = [], $page = 1): Response
    {
        $this->profiler?->disable();

        return self::send($this->editorJs->users($data, $request->getMethod(), self::body($request), $page, $fields));
    }

    #[Route("/ux/editorjs/keyword/{data}/{page}", name:"endpointByKeyword")]
    public function EndpointByKeyword(Request $request, $data = null, array $fields = ["slug"], $page = 1): Response
    {
        $this->profiler?->disable();

        return self::send($this->editorJs->keywords($data, $request->getMethod(), self::body($request), $page, $fields));
    }

    #[Route("/ux/editorjs/thread/{data}/{page}", name:"endpointByThread")]
    public function EndpointByThread(Request $request, $data = null, array $fields = [], $page = 1): Response
    {
        $this->profiler?->disable();

        return self::send($this->editorJs->threads($data, $request->getMethod(), self::body($request), $page, $fields));
    }

    #[Route("/ux/editorjs/{data}", name:"uploadByFile")]
    public function UploadByFile(Request $request, $data = null): Response
    {
        $file = $request->files->get("image");

        return self::send($this->editorJs->uploadByFile($data, $file instanceof UploadedFile ? $file : null, $file instanceof UploadedFile ? $file->getError() : \UPLOAD_ERR_OK));
    }

    #[Route("/ux/editorjs/{data}/fetch", name:"uploadByUrl")]
    public function UploadByUrl(Request $request, $data = null): Response
    {
        $content = $request->getContent();
        $url = $content ? (json_decode($content)->url ?? null) : null;

        return self::send($this->editorJs->uploadByUrl($data, \is_string($url) ? $url : null));
    }

    /** A collaboration relay's ticket for one room (collab_live): EditorJs::ticket(). */
    #[Route("/ux/editorjs/collab-ticket", name:"collabTicket", methods:["POST"], priority: 1)]
    public function CollabTicket(Request $request): JsonResponse
    {
        return self::answer($this->editorJs->ticket(self::body($request)));
    }

    /** One field of one record saved outside its form (collab_autosave, the relay's saves): EditorJs::autosave(). */
    #[Route("/ux/editorjs/autosave", name:"autosave", methods:["POST"], priority: 1)]
    public function Autosave(Request $request): JsonResponse
    {
        return self::answer($this->editorJs->autosave(self::body($request)));
    }

    /** @return array<string, mixed> */
    private static function body(Request $request): array
    {
        $body = json_decode($request->getContent(), true);

        return \is_array($body) ? $body : [];
    }

    /** The service's answer: its JSON, or its reason as text. */
    private static function send(Answer $answer): Response
    {
        return \is_array($answer->body) ? self::answer($answer) : new Response($answer->body, $answer->status);
    }

    private static function answer(Answer $answer): JsonResponse
    {
        return JsonResponse::fromJsonString((string) json_encode($answer->body), $answer->status);
    }
}
