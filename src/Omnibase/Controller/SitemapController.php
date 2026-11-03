<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Event\SitemapEvent;
use Base\Service\SitemapperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;

use Base\Traits\BaseTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends AbstractController
{
    use BaseTrait;

    #[Route("/sitemap.{extension}", name: "app_sitemap", requirements: ["extension" => "xml|txt"])]
    public function Main(string $extension, Request $request, SitemapperInterface $sitemap, EventDispatcherInterface $dispatcher): Response
    {
        $hostname = $request->getSchemeAndHttpHost();

        // registerAttributes(), not registerMetadata(): the method was renamed
        // with the rest of the Annotation* -> Attribute* move and this call site
        // was left behind, so every /sitemap.xml answered "Call to undefined
        // method" - a 500 on the one URL a search engine is told to fetch.
        $sitemap
            ->setHostname($hostname)
            ->registerAttributes();

        // Attributes only cover parameterless routes. Entity URLs are the
        // application's to enumerate, so ask it.
        $dispatcher->dispatch(new SitemapEvent($sitemap, $hostname), SitemapEvent::BUILD);

        return $sitemap->serve('sitemap.' . $extension . '.twig');
    }
}
