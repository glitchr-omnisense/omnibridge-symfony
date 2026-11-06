<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Request\AttachmentDownload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A widget's attachment under Symfony: omnibase's Base\Request\AttachmentDownload decides (plan "omnibase agnostic",
 * step F5e slice 5b) - which file, its type -; downloaded under its slug. A widget's page (/page/{slug}) is an action
 * written once for every host: Base\Controller\Action\Widget\Page.
 */
class WidgetController extends AbstractController
{
    public function __construct(private readonly AttachmentDownload $download)
    {
    }

    #[Route("/attachment/{slug}", name: "widget_attachment")]
    public function Attachment($slug): BinaryFileResponse
    {
        $file = $this->download->file($slug) ?? throw $this->createNotFoundException("Attachment requested doesn't exist.");
        $response = new BinaryFileResponse($file->path);
        $response->headers->set('Content-Type', $file->type);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $slug);

        return $response;
    }
}
