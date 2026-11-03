<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Repository\Layout\Widget\AttachmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

class WidgetController extends AbstractController
{
    private AttachmentRepository $attachmentRepository;

    public function __construct(AttachmentRepository $attachmentRepository)
    {
        $this->attachmentRepository = $attachmentRepository;
    }

    // A widget's page (/page/{slug}) is an action written once for every host:
    // Base\Controller\Action\Widget\Page (audit "lesringostars face au master plan", R27).

    #[Route("/attachment/{slug}", name: "widget_attachment")]
    public function Attachment($slug): BinaryFileResponse
    {
        $attachment = $this->attachmentRepository->findOneBySlug($slug);
        if ($attachment === null) {
            throw $this->createNotFoundException("Attachment requested doesn't exist.");
        }

        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();

        $response = new BinaryFileResponse($attachment->getPublic());
        if ($mimeTypeGuesser->isGuesserSupported()) {
            $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($attachment->getPublic()));
        } else {
            $response->headers->set('Content-Type', 'text/plain');
        }

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getSlug());

        return $response;
    }
}
