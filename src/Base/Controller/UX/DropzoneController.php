<?php

namespace Base\Controller\UX;

use Base\Field\Search\Answer;
use Base\Field\Upload\Dropzone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

/**
 * A dropzone's uploads under Symfony: Base\Field\Upload\Dropzone answers each action (plan
 * "omnibase agnostic", step F5d), the same omnibridge/laravel's UploadController asks.
 */
#[Route(priority: -1)]
class DropzoneController extends AbstractController
{
    public const CACHE_DURATION = 24 * 3600;

    public const STATUS_OK = Dropzone::STATUS_OK;
    public const STATUS_BAD = Dropzone::STATUS_BAD;
    public const STATUS_NOTOKEN = Dropzone::STATUS_NOTOKEN;

    public function __construct(protected Dropzone $dropzone)
    {
    }

    /** The folder above the uploads' (Dropzone::directory() is its dropzone/). */
    public function getCacheDir(): string
    {
        return \dirname($this->dropzone->directory());
    }

    #[Route('/ux/dropzone/{data}', name: 'ux_dropzone')]
    public function Main(Request $request, $data = null): Response
    {
        $file = $request->files->get('file');
        $answer = $this->dropzone->store((string) $data, $file instanceof UploadedFile ? $file : null, $file instanceof UploadedFile ? $file->getError() : \UPLOAD_ERR_OK);

        return self::send($answer);
    }

    #[Route('/ux/dropzone/{data}/{uuid}', name: 'ux_dropzone_preview')]
    public function Preview(string $data, string $uuid): Response
    {
        $path = $this->dropzone->preview($data, $uuid);
        if ($path instanceof Answer) {
            return self::send($path);
        }
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new Response();
        $response->setContent(file_get_contents2($path));
        $response->setMaxAge(300);
        $response->setPublic();
        $response->setEtag(md5($response->getContent()));
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set('Content-Type', mime_content_type2($path));

        return $response;
    }

    #[Route('/ux/dropzone/{data}/{uuid}/delete', name: 'ux_dropzone_delete')]
    public function Delete(string $data, string $uuid): Response
    {
        return self::send($this->dropzone->delete($data, $uuid));
    }

    /** The service's answer: its JSON, or its reason as text; a payload without a token, Symfony's CSRF refusal. */
    private static function send(Answer $answer): Response
    {
        if (403 === $answer->status) {
            throw new InvalidCsrfTokenException();
        }

        return \is_array($answer->body) ? JsonResponse::fromJsonString(json_encode($answer->body)) : new Response($answer->body, $answer->status);
    }
}
