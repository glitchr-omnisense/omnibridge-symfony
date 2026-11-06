<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Request\SitemapPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The sitemap under Symfony: omnibase's Base\Request\SitemapPage gathers its pages (plan "omnibase agnostic", step F5e
 * slice 5b) - the routes' #[Sitemap], then the application's through SitemapEvent::BUILD -, on the request's origin.
 */
class SitemapController extends AbstractController
{
    #[Route("/sitemap.{extension}", name: "app_sitemap", requirements: ["extension" => "xml|txt"])]
    public function Main(string $extension, Request $request, SitemapPage $sitemap): Response
    {
        return $sitemap->build($request->getSchemeAndHttpHost())->serve(SitemapPage::template($extension));
    }
}
